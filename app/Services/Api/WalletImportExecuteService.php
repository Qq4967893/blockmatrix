<?php

namespace App\Services\Api;

use App\Models\Api\FundingOperation;
use App\Models\Api\FundingOperationRecord;
use App\Models\Api\FundingLedger;
use App\Models\Api\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class WalletImportExecuteService
{
    public function __construct(
        private FundingLedgerService $fundingLedgerService,
    ) {
    }

    public function dispatchExecute(int $jobId, string $activityName = '', string $reason = ''): array
    {
        if (! Schema::connection(DB_BM)->hasTable(FundingOperation::TABLE)
            || ! Schema::connection(DB_BM)->hasTable(FundingOperationRecord::TABLE)) {
            throw new \RuntimeException(trans('public.message.non_existent'));
        }

        $jobColumns = Schema::connection(DB_BM)->getColumnListing(FundingOperation::TABLE);
        $detailColumns = Schema::connection(DB_BM)->getColumnListing(FundingOperationRecord::TABLE);
        $executedAt = now()->format(Money::DATETIME_FORMAT);

        return DB::connection(DB_BM)->transaction(function () use ($jobId, $activityName, $reason, $jobColumns, $detailColumns, $executedAt): array {
            $job = DB::connection(DB_BM)
                ->table(FundingOperation::TABLE)
                ->where('id', $jobId)
                ->lockForUpdate()
                ->first();

            if (! $job) {
                throw new \RuntimeException(trans('public.message.non_existent'));
            }

            $status = (int) ($job->status ?? -1);
            if ($status !== FundingOperation::STATUS_PENDING) {
                throw new \RuntimeException(trans('public.message.state_error'));
            }

            $validCount = (int) ($job->valid_count ?? 0);
            if ($validCount <= 0) {
                throw new \RuntimeException('没有可执行的有效明细');
            }

            $pendingCount = (int) DB::connection(DB_BM)
                ->table(FundingOperationRecord::TABLE)
                ->where('job_id', $jobId)
                ->where('status', FundingOperationRecord::STATUS_PENDING)
                ->count();

            if ($pendingCount <= 0) {
                throw new \RuntimeException('没有待执行的明细记录');
            }

            if ($activityName !== '' && in_array('activity_name', $detailColumns, true)) {
                DB::connection(DB_BM)
                    ->table(FundingOperationRecord::TABLE)
                    ->where('job_id', $jobId)
                    ->where('status', FundingOperationRecord::STATUS_PENDING)
                    ->where(function ($query) {
                        $query->whereNull('activity_name')->orWhere('activity_name', '');
                    })
                    ->update($this->filterPayloadByColumns([
                        'activity_name' => $activityName,
                    ], $detailColumns));
            }

            if ($reason !== '' && in_array('reason', $detailColumns, true)) {
                DB::connection(DB_BM)
                    ->table(FundingOperationRecord::TABLE)
                    ->where('job_id', $jobId)
                    ->where('status', FundingOperationRecord::STATUS_PENDING)
                    ->where(function ($query) {
                        $query->whereNull('reason')->orWhere('reason', '');
                    })
                    ->update($this->filterPayloadByColumns([
                        'reason' => $reason,
                    ], $detailColumns));
            }

            $jobPayload = [
                'status' => FundingOperation::STATUS_PROCESSING,
                'executed_at' => $executedAt,
                'finished_at' => null,
            ];
            if ($activityName !== '') {
                $jobPayload['activity_name'] = $activityName;
            }
            if ($reason !== '') {
                $jobPayload['reason'] = $reason;
            }
            $jobPayload = $this->filterPayloadByColumns($jobPayload, $jobColumns);

            if ($jobPayload === []) {
                throw new \RuntimeException(trans('public.message.state_error'));
            }

            $updated = DB::connection(DB_BM)
                ->table(FundingOperation::TABLE)
                ->where('id', $jobId)
                ->where('status', FundingOperation::STATUS_PENDING)
                ->update($jobPayload);

            if ($updated <= 0) {
                throw new \RuntimeException(trans('public.message.state_error'));
            }

            $jobFresh = DB::connection(DB_BM)
                ->table(FundingOperation::TABLE)
                ->where('id', $jobId)
                ->first();

                return [
                    'id' => $jobId,
                    'status' => FundingOperation::STATUS_PROCESSING,
                    'status_text' => FundingOperation::statusTextMap()[FundingOperation::STATUS_PROCESSING] ?? '',
                    'activity_name' => $activityName !== '' ? $activityName : (string) ($jobFresh->activity_name ?? ''),
                    'reason' => $reason !== '' ? $reason : (string) ($jobFresh->reason ?? ''),
                    'executed_at' => $executedAt,
                ];
            });
    }

    public function revertDispatchedState(int $jobId): void
    {
        $jobColumns = Schema::connection(DB_BM)->getColumnListing(FundingOperation::TABLE);
        DB::connection(DB_BM)
            ->table(FundingOperation::TABLE)
            ->where('id', $jobId)
            ->where('status', FundingOperation::STATUS_PROCESSING)
            ->update($this->filterPayloadByColumns([
                'status' => FundingOperation::STATUS_PENDING,
                'executed_at' => null,
            ], $jobColumns));
    }

    public function executeBatch(int $jobId): void
    {
        $job = DB::connection(DB_BM)
            ->table(FundingOperation::TABLE)
            ->where('id', $jobId)
            ->first();

        if (! $job) {
            throw new \RuntimeException('批次不存在');
        }

        if ((int) ($job->status ?? -1) !== FundingOperation::STATUS_PROCESSING) {
            throw new \RuntimeException('批次状态不是执行中');
        }

        $lastId = 0;
        while (true) {
            $detailIds = DB::connection(DB_BM)
                ->table(FundingOperationRecord::TABLE)
                ->where('job_id', $jobId)
                ->where('status', FundingOperationRecord::STATUS_PENDING)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(200)
                ->pluck('id')
                ->all();

            if ($detailIds === []) {
                break;
            }

            foreach ($detailIds as $detailId) {
                $detailId = (int) $detailId;
                $this->executeDetail($detailId, (array) $job);
                $lastId = $detailId;
            }
        }

        $this->refreshJobSummary($jobId);
    }

    public function markJobAbnormal(int $jobId, string $message): void
    {
        $jobColumns = Schema::connection(DB_BM)->getColumnListing(FundingOperation::TABLE);
        $now = now()->format(Money::DATETIME_FORMAT);
        $remark = '执行异常待人工核查';
        $message = trim($message);
        if ($message !== '') {
            $remark .= ': ' . $this->truncateMessage($message);
        }

        DB::connection(DB_BM)
            ->table(FundingOperation::TABLE)
            ->where('id', $jobId)
            ->where('status', FundingOperation::STATUS_PROCESSING)
            ->update($this->filterPayloadByColumns([
                'status' => FundingOperation::STATUS_FAIL,
                'remark' => $remark,
                'finished_at' => $now,
            ], $jobColumns));
    }

    private function executeDetail(int $detailId, array $job): void
    {
        $detailColumns = Schema::connection(DB_BM)->getColumnListing(FundingOperationRecord::TABLE);

        $detail = DB::connection(DB_BM)->transaction(function () use ($detailId, $detailColumns): ?array {
            $detail = DB::connection(DB_BM)
                ->table(FundingOperationRecord::TABLE)
                ->where('id', $detailId)
                ->lockForUpdate()
                ->first();

            if (! $detail) {
                return null;
            }

            $status = (int) ($detail->status ?? -1);
            if ($status !== FundingOperationRecord::STATUS_PENDING) {
                return is_array($detail) ? $detail : (array) $detail;
            }

            DB::connection(DB_BM)
                ->table(FundingOperationRecord::TABLE)
                ->where('id', $detailId)
                ->where('status', FundingOperationRecord::STATUS_PENDING)
                ->update($this->filterPayloadByColumns([
                    'status' => FundingOperationRecord::STATUS_PROCESSING,
                ], $detailColumns));

            return is_array($detail) ? $detail : (array) $detail;
        });

        if ($detail === null || (int) ($detail['status'] ?? -1) !== FundingOperationRecord::STATUS_PENDING) {
            return;
        }

        $userCode = (int) ($detail['user_code'] ?? 0);
        $amount = (string) ($detail['amount'] ?? '0.000000');
        $activityName = (string) ($detail['activity_name'] ?? '');
        $reason = (string) ($detail['reason'] ?? '');
        $fundType = (int) ($job['fund_type'] ?? 0);
        $direction = (int) ($job['direction'] ?? 0);
        try {
            if ($direction === FundingLedger::DIRECTION_IN) {
                $result = $this->fundingLedgerService->transferFunds(
                    $userCode,
                    $fundType,
                    $amount,
                    $activityName,
                    $reason,
                    $detailId
                );
            } else {
                $result = $this->fundingLedgerService->deductFunds(
                    $userCode,
                    $fundType,
                    $amount,
                    $activityName,
                    $reason,
                    $detailId
                );
            }

            DB::connection(DB_BM)
                ->table(FundingOperationRecord::TABLE)
                ->where('id', $detailId)
                ->update($this->filterPayloadByColumns([
                    'status' => FundingOperationRecord::STATUS_SUCCESS,
                    'error_message' => '',
                    'ledger_id' => $result['ledger_id'] ?? null,
                ], $detailColumns));
        } catch (Throwable $e) {
            Log::error('Wallet import detail execution failed.', [
                'job_id' => (int) ($job['id'] ?? 0),
                'detail_id' => $detailId,
                'user_code' => $userCode,
                'direction' => $direction,
                'fund_type' => $fundType,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            DB::connection(DB_BM)
                ->table(FundingOperationRecord::TABLE)
                ->where('id', $detailId)
                ->update($this->filterPayloadByColumns([
                    'status' => FundingOperationRecord::STATUS_FAIL,
                    'error_message' => $this->truncateMessage($e->getMessage()),
                ], $detailColumns));
        }
    }

    /**
     * @param array<int, string>|null $jobColumns
     */
    private function refreshJobSummary(int $jobId, ?array $jobColumns = null, ?string $finishedAt = null): void
    {
        $jobColumns ??= Schema::connection(DB_BM)->getColumnListing(FundingOperation::TABLE);
        $finishedAt ??= now()->format(Money::DATETIME_FORMAT);

        $summaryRows = DB::connection(DB_BM)
            ->table(FundingOperationRecord::TABLE)
            ->where('job_id', $jobId)
            ->select('status', DB::raw('COUNT(*) as aggregate_count'), DB::raw('COALESCE(SUM(amount), 0) as aggregate_amount'))
            ->groupBy('status')
            ->get()
            ->map(static fn ($item) => (array) $item)
            ->all();

        $summaryMap = [];
        foreach ($summaryRows as $row) {
            $summaryMap[(int) ($row['status'] ?? -1)] = [
                'count' => (int) ($row['aggregate_count'] ?? 0),
                'amount' => (string) ($row['aggregate_amount'] ?? '0'),
            ];
        }

        $successCount = (int) ($summaryMap[FundingOperationRecord::STATUS_SUCCESS]['count'] ?? 0);
        $execFailCount = (int) ($summaryMap[FundingOperationRecord::STATUS_FAIL]['count'] ?? 0);
        $invalidCount = (int) ($summaryMap[FundingOperationRecord::STATUS_INVALID]['count'] ?? 0);
        $pendingOrProcessing = (int) ($summaryMap[FundingOperationRecord::STATUS_PENDING]['count'] ?? 0)
            + (int) ($summaryMap[FundingOperationRecord::STATUS_PROCESSING]['count'] ?? 0);
        $successAmount = $this->normalizeDecimalString((string) ($summaryMap[FundingOperationRecord::STATUS_SUCCESS]['amount'] ?? '0'));

        $finalStatus = FundingOperation::STATUS_FAIL;
        if ($pendingOrProcessing > 0) {
            $finalStatus = FundingOperation::STATUS_PROCESSING;
        } elseif ($successCount > 0 && ($execFailCount > 0 || $invalidCount > 0)) {
            $finalStatus = FundingOperation::STATUS_PARTIAL_SUCCESS;
        } elseif ($successCount > 0) {
            $finalStatus = FundingOperation::STATUS_SUCCESS;
        }

        DB::connection(DB_BM)
            ->table(FundingOperation::TABLE)
            ->where('id', $jobId)
            ->update($this->filterPayloadByColumns([
                'status' => $finalStatus,
                'success_count' => $successCount,
                'fail_count' => $execFailCount + $invalidCount,
                'success_amount' => $successAmount,
                'finished_at' => $pendingOrProcessing > 0 ? null : $finishedAt,
            ], $jobColumns));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $columns
     * @return array<string, mixed>
     */
    private function filterPayloadByColumns(array $payload, array $columns): array
    {
        return array_intersect_key($payload, array_flip($columns));
    }

    private function normalizeDecimalString(string $amount): string
    {
        if (! preg_match('/^-?\d+(?:\.\d{1,6})?$/', trim($amount))) {
            return '0.000000';
        }

        $negative = str_starts_with($amount, '-');
        $amount = ltrim(trim($amount), '-');
        [$integerPart, $decimalPart] = array_pad(explode('.', $amount, 2), 2, '');
        $decimalPart = str_pad(substr($decimalPart, 0, 6), 6, '0');

        return ($negative ? '-' : '') . ((int) $integerPart) . '.' . $decimalPart;
    }

    private function truncateMessage(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return '执行失败';
        }

        return mb_substr($message, 0, 255);
    }
}
