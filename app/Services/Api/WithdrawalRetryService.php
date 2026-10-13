<?php

namespace App\Services\Api;

use App\Models\Api\FundingApply;
use App\Models\Api\Money;
use App\Repositories\Api\FundingApplyRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Scaffold\FluxWallet\Exceptions\FluxWalletException;
use Scaffold\Funding\Enums\FundingApplyStatus;
use Scaffold\Funding\Enums\FundingFinanceStatus;
use Scaffold\Funding\Enums\FundingRiskStatus;

class WithdrawalRetryService
{
    public function __construct(
        private FundingApplyRepository $fundingApplyRepository,
    ) {}

    /**
     * 稳定幂等键：一单终身不变，与存量 uniqid 后缀永不冲突。
     */
    public function buildRequestId(int $orderNo): string
    {
        return $orderNo.'@retry';
    }

    /**
     * 仅 Walletd 明确返回未提交 PSP 时才允许重试；其余错误一律保持原有失败逻辑。
     */
    public function isNotSubmittedError(FluxWalletException $exception): bool
    {
        return $this->isNotSubmittedCode($exception->getErrorCode());
    }

    public function isNotSubmittedCode(string $code): bool
    {
        return $code === FundingApply::WALLET_STATUS_NOT_SUBMITTED;
    }

    /**
     * @param  array{status?: mixed}  $withdrawalResult
     */
    public function isNotSubmittedResult(array $withdrawalResult): bool
    {
        return (string) ($withdrawalResult['status'] ?? '') === FundingApply::WALLET_STATUS_NOT_SUBMITTED;
    }

    /**
     * 首次发起钱包出金前持久化幂等键：先落库、后发请求，超时未知态下仍可安全复用同一 request_id。
     *
     * @return array{ledger: array, request_id: string}
     */
    public function prepareInitialDispatch(int $orderNo): array
    {
        return DB::connection(DB_API)->transaction(function () use ($orderNo): array {
            $ledger = $this->lockWithdrawalInProcessingWindow($orderNo);

            $requestId = $this->ensureRequestIdPersisted($ledger);

            return [
                'ledger' => $ledger,
                'request_id' => $requestId,
            ];
        });
    }

    /**
     * 自动/手动重试前置检查：状态仍处重试窗口且到期才可执行，执行前推进租约防止并发重复发起。
     *
     * @return array{ledger: array, request_id: string}|null null 表示当前不可重试（未到期/状态已变化）
     */
    public function prepareRetryAttempt(int $orderNo, string $trigger): ?array
    {
        return DB::connection(DB_API)->transaction(function () use ($orderNo, $trigger): ?array {
            $ledger = $this->lockWithdrawalInProcessingWindow($orderNo);
            $state = $this->parseWalletDispatch($ledger);

            if (($state['retry_phase'] ?? '') !== FundingApply::WALLET_RETRY_PHASE_RETRYING) {
                return null;
            }

            if (! $this->isNotSubmittedState($ledger)) {
                return null;
            }

            $requestId = (string) ($state['request_id'] ?? '');
            if ($requestId === '') {
                return null;
            }

            $now = CarbonImmutable::now('UTC');
            $retryLeaseUntil = trim((string) ($state['retry_lease_until'] ?? ''));
            if ($retryLeaseUntil !== '' && CarbonImmutable::parse($retryLeaseUntil, 'UTC')->isFuture()) {
                if ($trigger === FundingApply::WALLET_RETRY_TRIGGER_MANUAL) {
                    throw new \Exception(trans('withdrawal.message.retry_in_progress'));
                }

                return null;
            }

            $nextRetryAt = CarbonImmutable::parse((string) ($state['next_retry_at'] ?? ''), 'UTC');
            if ($trigger === FundingApply::WALLET_RETRY_TRIGGER_AUTO && ($nextRetryAt->isFuture() || $this->autoCount($state) >= FundingApply::MAX_AUTO_RETRY_COUNT)) {
                return null;
            }

            // 占位租约：请求返回后会清理；若进程中断，租约到期后可被重新扫描。
            $state['retry_lease_until'] = $now->addSeconds(FundingApply::WALLET_RETRY_CLAIM_LEASE_SECONDS)->format(Money::DATETIME_FORMAT);
            $this->writeWalletDispatch($orderNo, $state);

            return [
                'ledger' => $ledger,
                'request_id' => $requestId,
            ];
        });
    }

    /**
     * 首次发起钱包出金返回未提交：保持 Processing，安排第一次自动重试（不消耗自动次数）。
     *
     * @return array<string, mixed> 前端 retry 载荷
     */
    public function recordInitialRetryFailure(int $orderNo, string $errorCode, string $message): array
    {
        return $this->recordRetryFailure($orderNo, FundingApply::WALLET_RETRY_TRIGGER_INITIAL, $errorCode, $message);
    }

    /**
     * 自动重试消耗次数；手动重试不消耗次数。次数耗尽时返回 exhausted=true，由调用方落 failFinance。
     *
     * @return array<string, mixed>
     */
    public function recordRetryAttemptFailure(int $orderNo, string $trigger, string $errorCode, string $message): array
    {
        return $this->recordRetryFailure($orderNo, $trigger, $errorCode, $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function recordRetryFailure(int $orderNo, string $trigger, string $errorCode, string $message): array
    {
        return DB::connection(DB_API)->transaction(function () use ($orderNo, $trigger, $errorCode, $message): array {
            $ledger = $this->lockWithdrawalInProcessingWindow($orderNo);
            $state = $this->parseWalletDispatch($ledger);

            $count = $this->autoCount($state);
            if ($trigger === FundingApply::WALLET_RETRY_TRIGGER_AUTO) {
                $count++;
            }

            $exhausted = $count >= FundingApply::MAX_AUTO_RETRY_COUNT;
            $intervalIndex = min($count, count(FundingApply::AUTO_RETRY_INTERVALS) - 1);
            $interval = FundingApply::AUTO_RETRY_INTERVALS[$exhausted ? count(FundingApply::AUTO_RETRY_INTERVALS) - 1 : $intervalIndex];

            $state = array_merge($state, [
                'last_error_code' => $errorCode,
                'last_error_message' => $message,
                'auto_retry_count' => $count,
                'auto_max_attempts' => FundingApply::MAX_AUTO_RETRY_COUNT,
                'retry_phase' => $exhausted ? FundingApply::WALLET_RETRY_PHASE_EXHAUSTED : FundingApply::WALLET_RETRY_PHASE_RETRYING,
                'next_retry_at' => CarbonImmutable::now('UTC')->addSeconds($interval)->format(Money::DATETIME_FORMAT),
                'last_attempted_at' => CarbonImmutable::now('UTC')->format(Money::DATETIME_FORMAT),
            ]);
            unset($state['retry_lease_until']);

            $this->writeWalletDispatch($orderNo, $state);

            return $this->formatRetryPayload($state);
        });
    }

    /**
     * 钱包已受理（SUBMITTED）：清除重试调度，等待链上回调或人工确认 hash。
     */
    public function recordSubmitted(int $orderNo): void
    {
        DB::connection(DB_API)->transaction(function () use ($orderNo): void {
            $ledger = $this->lockWithdrawalInProcessingWindow($orderNo);
            $state = $this->parseWalletDispatch($ledger);

            $state['retry_phase'] = FundingApply::WALLET_RETRY_PHASE_SUBMITTED;
            $state['next_retry_at'] = null;
            unset($state['retry_lease_until']);
            $state['last_error_code'] = null;
            $state['last_error_message'] = null;
            $state['last_attempted_at'] = CarbonImmutable::now('UTC')->format(Money::DATETIME_FORMAT);

            $this->writeWalletDispatch($orderNo, $state);
        });
    }

    /**
     * 人工驳回/确认等接管场景：取消自动重试。
     */
    public function cancel(int $orderNo): void
    {
        DB::connection(DB_API)->transaction(function () use ($orderNo): void {
            $ledger = $this->fundingApplyRepository->lockWithdrawalByOrderNo($orderNo);
            if (empty($ledger)) {
                return;
            }

            $state = $this->parseWalletDispatch($ledger);
            if (($state['retry_phase'] ?? '') !== FundingApply::WALLET_RETRY_PHASE_RETRYING) {
                return;
            }

            $state['retry_phase'] = FundingApply::WALLET_RETRY_PHASE_CANCELLED;
            $state['next_retry_at'] = null;
            unset($state['retry_lease_until']);
            $this->writeWalletDispatch($orderNo, $state);
        });
    }

    /**
     * 扫描重试窗口内的到期订单（状态过滤 + JSON 预过滤，内存精确判定由调用方完成）。
     *
     * @return array<int, array{id:int, order_no:string, user_code:int}>
     */
    public function getDueRetryCandidates(CarbonImmutable $now, int $limit): array
    {
        $records = $this->fundingApplyRepository->getWithdrawalRetryWindowCandidates($limit);

        return collect($records)
            ->filter(function (array $record) use ($now): bool {
                $state = $this->parseWalletDispatch($record);
                if (($state['retry_phase'] ?? '') !== FundingApply::WALLET_RETRY_PHASE_RETRYING) {
                    return false;
                }

                if (! $this->isNotSubmittedState($record)) {
                    return false;
                }

                $nextRetryAt = trim((string) ($state['next_retry_at'] ?? ''));
                if ($nextRetryAt === '') {
                    return false;
                }

                $retryLeaseUntil = trim((string) ($state['retry_lease_until'] ?? ''));
                if ($retryLeaseUntil !== '' && CarbonImmutable::parse($retryLeaseUntil, 'UTC')->isFuture()) {
                    return false;
                }

                return CarbonImmutable::parse($nextRetryAt, 'UTC')->lte($now)
                    && $this->autoCount($state) < FundingApply::MAX_AUTO_RETRY_COUNT;
            })
            ->map(static fn (array $record): array => [
                'id' => (int) ($record['id'] ?? 0),
                'order_no' => (string) ($record['order_no'] ?? ''),
                'user_code' => (int) ($record['user_code'] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function formatRetryPayload(array $state): array
    {
        $phase = (string) ($state['retry_phase'] ?? '');
        $lastErrorCode = (string) ($state['last_error_code'] ?? '');
        $retryable = $lastErrorCode === FundingApply::WALLET_STATUS_NOT_SUBMITTED;
        $count = $this->autoCount($state);
        $retryLeaseUntil = trim((string) ($state['retry_lease_until'] ?? ''));

        return [
            'request_id' => (string) ($state['request_id'] ?? ''),
            'retryable' => $retryable,
            'retry_phase' => $phase !== '' ? $phase : FundingApply::WALLET_RETRY_PHASE_NONE,
            'auto_retry_count' => $count,
            'auto_max_attempts' => FundingApply::MAX_AUTO_RETRY_COUNT,
            'next_retry_at' => $state['next_retry_at'] ?? null,
            'retry_in_progress' => $retryLeaseUntil !== ''
                && CarbonImmutable::parse($retryLeaseUntil, 'UTC')->isFuture(),
            'last_error_code' => $lastErrorCode !== '' ? $lastErrorCode : null,
            'last_error_message' => $state['last_error_message'] ?? null,
        ];
    }

    /**
     * 自动重试窗口内的订单必须是 Walletd 明确返回未提交 PSP 的订单。
     *
     * @param  array<string, mixed>  $ledger
     */
    public function isNotSubmittedState(array $ledger): bool
    {
        $state = $this->parseWalletDispatch($ledger);

        return (string) ($state['last_error_code'] ?? '') === FundingApply::WALLET_STATUS_NOT_SUBMITTED;
    }

    /**
     * 失败单只有在 WITHDRAWAL_NOT_SUBMITTED 自动重试耗尽后才允许人工重新发起。
     *
     * @param  array<string, mixed>  $ledger
     */
    public function isExhaustedNotSubmittedFailure(array $ledger): bool
    {
        $state = $this->parseWalletDispatch($ledger);

        return ($state['retry_phase'] ?? '') === FundingApply::WALLET_RETRY_PHASE_EXHAUSTED
            && (string) ($state['last_error_code'] ?? '') === FundingApply::WALLET_STATUS_NOT_SUBMITTED;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function autoCount(array $state): int
    {
        return max(0, (int) ($state['auto_retry_count'] ?? 0));
    }

    /**
     * 锁定并校验单据仍处风控已通过、财务处理中的重试窗口。
     *
     * @return array<string, mixed>
     */
    private function lockWithdrawalInProcessingWindow(int $orderNo): array
    {
        $ledger = $this->fundingApplyRepository->lockWithdrawalByOrderNo($orderNo);
        if (empty($ledger)) {
            throw new \Exception(trans('public.message.not_found'));
        }

        $status = (int) ($ledger['status'] ?? -1);
        $riskStatus = (int) ($ledger['risk_status'] ?? -1);
        $financeStatus = (int) ($ledger['finance_status'] ?? 0);
        if (
            $status !== FundingApplyStatus::Processing->value
            || $riskStatus !== FundingRiskStatus::Approved->value
            || $financeStatus !== FundingFinanceStatus::Pending->value
        ) {
            throw new \Exception(trans('public.message.final_status'));
        }

        return $ledger;
    }

    /**
     * 幂等键不存在则写入（首发前持久化；已有值终身复用）。
     *
     * @param  array<string, mixed>  $ledger
     */
    private function ensureRequestIdPersisted(array $ledger): string
    {
        $state = $this->parseWalletDispatch($ledger);
        $requestId = (string) ($state['request_id'] ?? '');

        if ($requestId === '') {
            $requestId = $this->buildRequestId((int) ($ledger['order_no'] ?? 0));
            $state['request_id'] = $requestId;
            $this->writeWalletDispatch((int) ($ledger['order_no'] ?? 0), $state);
        }

        return $requestId;
    }

    /**
     * @param  array<string, mixed>  $ledger
     * @return array<string, mixed>
     */
    public function parseWalletDispatch(array $ledger): array
    {
        $referenceContent = $ledger['reference_content'] ?? null;
        if (is_array($referenceContent)) {
            $decoded = $referenceContent;
        } else {
            $decoded = json_decode((string) $referenceContent, true);
        }

        if (! is_array($decoded)) {
            return [];
        }

        $walletDispatch = $decoded['wallet_dispatch'] ?? null;

        return is_array($walletDispatch) ? $walletDispatch : [];
    }

    /**
     * 锁内合并写入 wallet_dispatch 节点，保留 reference_content 其它键。
     *
     * @param  array<string, mixed>  $state
     */
    private function writeWalletDispatch(int $orderNo, array $state): void
    {
        $this->fundingApplyRepository->mergeWithdrawalWalletDispatch($orderNo, $state);
    }
}
