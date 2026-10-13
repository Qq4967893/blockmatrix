<?php

namespace App\Services\Api;

use App\Repositories\Api\IbApplicationRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Enums\TypeIbApplicationExchange;
use Scaffold\Framework\Enums\TypeIbApplicationStatus;
use Scaffold\Framework\Utils\S3;

class IbApplicationService
{
    public function __construct(
        private IbApplicationRepository $applicationRepo,
        private AdminUserRepository $adminUserRepo,
        private IbAccountService $ibAccountService,
    ) {
    }

    /**
     * @param array{
     *     user_code?: int|null,
     *     applied_at_start?: string|null,
     *     applied_at_end?: string|null,
     *     status?: int|null,
     *     operator_name?: string|null
     * } $filters
     * @return array{data: array<int, array>, total_count: int}
     */
    public function getList(array $filters, int $limit, int $offset): array
    {
        $reviewedByIds = null;
        $operatorName = trim((string) ($filters['operator_name'] ?? ''));
        if ($operatorName !== '') {
            $reviewedByIds = $this->adminUserRepo->searchIdsByLoginName($operatorName);
        }

        [$rows, $count] = $this->applicationRepo->getList([
            'user_code' => $filters['user_code'] ?? null,
            'applied_at_start' => $filters['applied_at_start'] ?? null,
            'applied_at_end' => $filters['applied_at_end'] ?? null,
            'status' => $filters['status'] ?? null,
            'reviewed_by_ids' => $reviewedByIds,
        ], $limit, $offset);

        if (empty($rows)) {
            return [
                'data' => [],
                'total_count' => 0,
            ];
        }

        $adminIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int) ($row['reviewed_by'] ?? 0),
            $rows
        ))));
        $adminNameMap = $this->adminUserRepo->getColumnByIds($adminIds, 'login_name');

        $data = array_map(function (array $row) use ($adminNameMap): array {
            $status = $this->normalizeStatus($row['status'] ?? null);
            $exchange = $this->normalizeExchange($row['primary_exchange'] ?? null);
            $reviewedBy = (int) ($row['reviewed_by'] ?? 0);

            return [
                'id' => (int) ($row['id'] ?? 0),
                'user_code' => (int) ($row['user_code'] ?? 0),
                'contact_type' => (int) ($row['contact_type'] ?? 0),
                'contact_value' => (string) ($row['contact_value'] ?? ''),
                'influence_proof' => $this->normalizeInfluenceProof($row['influence_proof'] ?? []),
                'primary_exchange' => $exchange,
                'primary_exchange_text' => $this->exchangeText($exchange),
                'position_size_last_3m' => $this->formatDecimal($row['position_size_last_3m'] ?? '0'),
                'promotion_description' => $this->nullableString($row['promotion_description'] ?? null),
                'applied_at' => $this->formatDateTime($row['applied_at'] ?? null),
                'operator_name' => (string) ($adminNameMap[$reviewedBy] ?? ''),
                'status' => $status,
                'status_text' => $this->statusText($status),
                'rejection_reason' => $this->nullableString($row['rejection_reason'] ?? null),
            ];
        }, $rows);

        return [
            'data' => $data,
            'total_count' => $count,
        ];
    }

    /**
     * @return array{pending_count: int}
     */
    public function getPendingCount(): array
    {
        return [
            'pending_count' => $this->applicationRepo->countByStatus(TypeIbApplicationStatus::Pending->value),
        ];
    }

    public function approve(int $id): array
    {
        $application = $this->findPendingApplicationOrFail($id);

        DB::connection(DB_API)->transaction(function () use ($application): void {
            $this->ibAccountService->createPrimaryAccountForUser(
                (int) $application->user_code,
                $application->applied_at ?? now()
            );

            $updated = $this->applicationRepo->updateApplication((int) $application->id, [
                'status' => TypeIbApplicationStatus::Approved->value,
                'reviewed_at' => now(),
                'reviewed_by' => $this->operatorId(),
                'rejection_reason' => null,
            ], $application);

            if (! $updated) {
                throw new \Exception(trans('public.message.state_error'));
            }
        });

        return [
            'id' => $id,
            'status' => TypeIbApplicationStatus::Approved->value,
            'status_text' => $this->statusText(TypeIbApplicationStatus::Approved->value),
        ];
    }

    public function reject(int $id, string $rejectionReason): array
    {
        $application = $this->findPendingApplicationOrFail($id);

        $updated = $this->applicationRepo->updateApplication((int) $application->id, [
            'status' => TypeIbApplicationStatus::Rejected->value,
            'reviewed_at' => now(),
            'reviewed_by' => $this->operatorId(),
            'rejection_reason' => $rejectionReason,
        ], $application);

        if (! $updated) {
            throw new \Exception(trans('public.message.state_error'));
        }

        return [
            'id' => $id,
            'status' => TypeIbApplicationStatus::Rejected->value,
            'status_text' => $this->statusText(TypeIbApplicationStatus::Rejected->value),
            'rejection_reason' => $rejectionReason,
        ];
    }

    private function findPendingApplicationOrFail(int $id): object
    {
        $application = $this->applicationRepo->findById($id);
        if (! $application) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        $status = $this->normalizeStatus($application->status ?? null);
        if ($status !== TypeIbApplicationStatus::Pending->value) {
            throw new \Exception($this->resolveStatusMessage($status));
        }

        return $application;
    }

    private function normalizeStatus(mixed $value): int
    {
        return $value instanceof TypeIbApplicationStatus
            ? $value->value
            : (int) ($value ?? TypeIbApplicationStatus::Pending->value);
    }

    private function normalizeExchange(mixed $value): int
    {
        $exchange = $value instanceof TypeIbApplicationExchange
            ? $value->value
            : (int) ($value ?? 0);

        return in_array($exchange, [
            TypeIbApplicationExchange::Bybit->value,
            TypeIbApplicationExchange::Bitget->value,
            TypeIbApplicationExchange::OKX->value,
            TypeIbApplicationExchange::Binance->value,
            TypeIbApplicationExchange::Other->value,
        ], true) ? $exchange : 0;
    }

    /**
     * @param array<int, mixed>|string|null $value
     * @return array<int, mixed>
     */
    private function normalizeInfluenceProof(array|string|null $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $item): mixed {
            if (is_array($item)) {
                return $this->normalizeProofItem($item);
            }

            if (is_object($item)) {
                return $this->normalizeProofItem((array) $item);
            }

            $text = trim((string) $item);

            return $text === '' ? null : $text;
        }, $value), static fn (mixed $item): bool => $item !== null));
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function normalizeProofItem(array $item): array
    {
        $proofFileKey = trim((string) ($item['proof_file_key'] ?? ''));
        if ($proofFileKey !== ''
            && ! str_starts_with($proofFileKey, 'http://')
            && ! str_starts_with($proofFileKey, 'https://')
        ) {
            $item['proof_file_key'] = (string) app(S3::class)->fileUrl($proofFileKey);
        }

        return $item;
    }

    private function exchangeText(int $exchange): string
    {
        return match ($exchange) {
            TypeIbApplicationExchange::Bybit->value => 'Bybit',
            TypeIbApplicationExchange::Bitget->value => 'Bitget',
            TypeIbApplicationExchange::OKX->value => 'OKX',
            TypeIbApplicationExchange::Binance->value => 'Binance',
            TypeIbApplicationExchange::Other->value => 'Other',
            default => '',
        };
    }

    private function statusText(int $status): string
    {
        return match ($status) {
            TypeIbApplicationStatus::Approved->value => '已通过',
            TypeIbApplicationStatus::Rejected->value => '已拒绝',
            default => '待审核',
        };
    }

    private function resolveStatusMessage(int $status): string
    {
        return match ($status) {
            TypeIbApplicationStatus::Pending->value => trans('public.message.ib_application_pending'),
            TypeIbApplicationStatus::Approved->value => trans('public.message.ib_application_approved'),
            TypeIbApplicationStatus::Rejected->value => trans('public.message.ib_application_rejected'),
            default => trans('public.message.state_error'),
        };
    }

    private function operatorId(): int
    {
        return (int) (Auth::id() ?? 0);
    }

    private function formatDecimal(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '0.000000';
        }

        return number_format((float) $value, 6, '.', '');
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
