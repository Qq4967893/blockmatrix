<?php

namespace App\Services\Api;

use App\Models\Api\FundingApply;
use App\Models\Api\Money;
use App\Models\Api\User;
use App\Repositories\Api\FundingApplyRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Enums\TypeLedger;
use Scaffold\Framework\Utils\Calc;
use Scaffold\Funding\Enums\FundingApplyStatus;
use Scaffold\Funding\Enums\FundingFinanceStatus;
use Scaffold\Funding\Enums\FundingRiskStatus;
use Scaffold\Funding\Facades\Funding as FundingFacade;
use Scaffold\FluxWallet\Facades\FluxWallet;
use Scaffold\FluxWallet\Exceptions\FluxWalletException;
use Illuminate\Support\Facades\Log;

class FundingApplyService
{
    public function __construct(
        private FundingApplyRepository $fundingApplyRepository,
        private FundingNetworkConfigService $fundingNetworkConfigService,
        private AdminUserRepository $adminUserRepo,
        private WithdrawalRetryService $withdrawalRetryService,
    ) {
    }

    /**
     * 入金筛选项：方向、通道、状态与入金状态列表
     *
     * @return array{deposit_types: array<int, array{code:int, name:string}>, platforms: array<int, array{code:string, name:string}>, statuses: array<int, array{code:int, name:string}>, deposit_statuses: array<int, array{code:string, name:string}>}
     */
    public function getFilterOptions(): array
    {
        $depositTypes = [
            [
                'code' => FundingApply::DIRECTION_DEPOSIT,
                'name' => trans('deposit.direction.' . FundingApply::DIRECTION_DEPOSIT),
            ],
        ];

        $statuses = array_map(function (int $code) {
            return [
                'code' => $code,
                'name' => trans('deposit.status.' . $code),
            ];
        }, FundingApply::DEPOSIT_STATUS_OPTIONS);

        $depositStatuses = array_map(function (string $code) {
            return [
                'code' => $code,
                'name' => trans('deposit.deposit_status.' . $code),
            ];
        }, FundingApply::DEPOSIT_STATUS_FILTER_OPTIONS);

        return [
            'deposit_types' => $depositTypes,
            'platforms'     => $this->fundingNetworkConfigService->options()['channels'] ?? [],
            'statuses'      => $statuses,
            'deposit_statuses' => $depositStatuses,
        ];
    }

    /**
     * 出金风控/财务筛选项
     *
     * @return array{
     *     platforms: array<int, array{code:string, name:string}>,
     *     risk_statuses: array<int, array{code:string, name:string}>,
     *     finance_statuses: array<int, array{code:string, name:string}>
     * }
     */
    public function getWithdrawalOptions(): array
    {
        $riskStatuses = array_map(function (FundingRiskStatus $status) {
            $code = strtolower($status->name);

            return [
                'code' => $code,
                'name' => trans('withdrawal.risk_status.' . $code),
            ];
        }, FundingRiskStatus::cases());

        $financeStatuses = array_map(function (FundingFinanceStatus $status) {
            $code = strtolower($status->name);

            return [
                'code' => $code,
                'name' => trans('withdrawal.finance_status.' . $code),
            ];
        }, FundingFinanceStatus::cases());

        return [
            'platforms' => $this->fundingNetworkConfigService->options()['channels'] ?? [],
            'risk_statuses' => $riskStatuses,
            'finance_statuses' => $financeStatuses,
        ];
    }

    /**
     * @param array{
     *     user_code?: int|string|null,
     *     status?: int|string|null,
     *     platform?: string|null,
     *     deposit_status?: string|null,
     *     direction?: int|string|null,
     *     received_time_start?: string|null,
     *     received_time_end?: string|null
     * } $filters
     * @return array{data: array<int, array>, total_count: int}
     */
    public function getDepositList(array $filters, int $limit, int $offset): array
    {
        [$records, $count] = $this->fundingApplyRepository->paginateDeposit(
            [
                'user_code' => $filters['user_code'] ?? null,
                'direction' => $filters['direction'] ?? FundingApply::DIRECTION_DEPOSIT,
                'status' => $filters['status'] ?? null,
                'platform' => $filters['platform'] ?? null,
                'deposit_status' => $filters['deposit_status'] ?? null,
                'received_time_start' => $filters['received_time_start'] ?? null,
                'received_time_end' => $filters['received_time_end'] ?? null,
            ],
            $limit,
            $offset
        );

        $successfulDepositCounts = $this->fundingApplyRepository->getSuccessfulDepositCounts(
            array_values(array_unique(array_filter(array_map(
                fn (array $record): int => (int) ($record['user_code'] ?? 0),
                $records
            ))))
        );

        return [
            'data' => array_map(function (array $record) use ($successfulDepositCounts) {
                $record['successful_deposit_count'] = $successfulDepositCounts[(int) ($record['user_code'] ?? 0)] ?? 0;

                return $this->formatCommonRow($record, false, [], [], true);
            }, $records),
            'total_count' => $count,
        ];
    }

    /**
     * @param array{
     *     user_code?: int|string|null,
     *     order_no?: int|string|null,
     *     status?: int|string|null,
     *     platform?: string|null,
     *     withdraw_time_start?: string|null,
     *     withdraw_time_end?: string|null
     * } $filters
     * @return array{data: array<int, array>, total_count: int}
     */
    public function getWithdrawalRiskList(array $filters, int $limit, int $offset): array
    {
        [$records, $count] = $this->fundingApplyRepository->paginateWithdrawalRisk(
            [
                'user_code' => $filters['user_code'] ?? null,
                'order_no' => $filters['order_no'] ?? null,
                'status' => $this->resolveRiskStatusFilterValue($filters['status'] ?? null),
                'platform' => $filters['platform'] ?? null,
                'withdraw_time_start' => $filters['withdraw_time_start'] ?? null,
                'withdraw_time_end' => $filters['withdraw_time_end'] ?? null,
            ],
            $limit,
            $offset
        );

        $riskAuditedByIds = array_values(array_unique(array_filter(array_map(
            static fn (array $record): int => (int) ($record['risk_audited_by'] ?? 0),
            $records
        ))));
        $riskOperatorNames = $this->adminUserRepo->getColumnByIds($riskAuditedByIds, 'login_name');

        return [
            'data' => array_map(
                fn (array $record) => $this->formatCommonRow($record, false, $riskOperatorNames),
                $records
            ),
            'total_count' => $count,
        ];
    }

    /**
     * @param array{
     *     user_code?: int|string|null,
     *     order_no?: int|string|null,
     *     status?: int|string|null,
     *     platform?: string|null,
     *     withdraw_time_start?: string|null,
     *     withdraw_time_end?: string|null
     * } $filters
     * @return array{data: array<int, array>, total_count: int}
     */
    public function getWithdrawalFinanceList(array $filters, int $limit, int $offset): array
    {
        [$records, $count] = $this->fundingApplyRepository->paginateWithdrawalFinance(
            [
                'user_code' => $filters['user_code'] ?? null,
                'order_no' => $filters['order_no'] ?? null,
                'status' => $this->resolveFinanceStatusFilterValue($filters['status'] ?? null),
                'platform' => $filters['platform'] ?? null,
                'withdraw_time_start' => $filters['withdraw_time_start'] ?? null,
                'withdraw_time_end' => $filters['withdraw_time_end'] ?? null,
            ],
            $limit,
            $offset
        );

        $financeAuditedByIds = array_values(array_unique(array_filter(array_map(
            static fn (array $record): int => (int) ($record['finance_audited_by'] ?? 0),
            $records
        ))));
        $financeOperatorNames = $this->adminUserRepo->getColumnByIds($financeAuditedByIds, 'login_name');

        return [
            'data' => array_map(
                fn (array $record) => $this->formatCommonRow($record, true, [], $financeOperatorNames),
                $records
            ),
            'total_count' => $count,
        ];
    }

    /**
     * 审核前读取出金申请单基础信息（用于风控限制判断等）
     *
     * @return array{order_no:int,user_code:int,platform:string}
     */
    public function getWithdrawalAuditMeta(int $orderNo): array
    {
        $ledger = $this->getWithdrawalByOrderNoOrFail($orderNo);

        return [
            'order_no' => (int) ($ledger['order_no'] ?? 0),
            'user_code' => (int) ($ledger['user_code'] ?? 0),
            'platform' => (string) ($ledger['platform'] ?? ''),
        ];
    }

    public function resolveWithdrawalOrderNoByIdOrFail(int $id): int
    {
        $ledger = $this->fundingApplyRepository->findWithdrawalById($id);
        if (empty($ledger)) {
            throw new \Exception(trans('public.message.not_found'));
        }

        return (int) ($ledger['order_no'] ?? 0);
    }

    /**
     * 风控审核通过：调用 Funding 风控通过，并发起钱包出金。
     */
    public function approveRisk(int $orderNo, int $auditedBy): array
    {
        return $this->approveRiskWhen($orderNo, $auditedBy, static fn (): bool => true) ?? [];
    }

    /**
     * 条件满足时原子地完成风控状态迁移，事务提交后再发起钱包出金。
     *
     * @param Closure(array): bool $shouldApprove
     */
    public function approveRiskWhen(int $orderNo, int $auditedBy, Closure $shouldApprove): ?array
    {
        $meta = $this->getWithdrawalByOrderNoOrFail($orderNo);

        $approval = DB::connection(DB_API)->transaction(function () use ($orderNo, $auditedBy, $meta, $shouldApprove): ?array {
            $this->fundingApplyRepository->lockWithdrawalUser((int) ($meta['user_code'] ?? 0));

            $ledger = $this->fundingApplyRepository->lockWithdrawalByOrderNo($orderNo);
            if (empty($ledger)) {
                throw new \Exception(trans('public.message.not_found'));
            }

            $this->assertRiskAuditable($ledger);
            if (! $shouldApprove($ledger)) {
                return null;
            }

            return [
                'ledger' => $ledger,
                'apply' => FundingFacade::approveRisk($orderNo, $auditedBy),
            ];
        });

        if ($approval === null) {
            return null;
        }

        return $this->dispatchApprovedWithdrawal($orderNo, $auditedBy, $approval);
    }

    /**
     * WITHDRAWAL_NOT_SUBMITTED 自动重试耗尽的财务失败单重新走风控通过并发起钱包出金。
     */
    public function retryFailedWithdrawal(int $orderNo, int $auditedBy): array
    {
        $meta = $this->getWithdrawalByOrderNoOrFail($orderNo);

        $approval = DB::connection(DB_API)->transaction(function () use ($orderNo, $auditedBy, $meta): array {
            $this->fundingApplyRepository->lockWithdrawalUser((int) ($meta['user_code'] ?? 0));

            $ledger = $this->fundingApplyRepository->lockWithdrawalByOrderNo($orderNo);
            if (empty($ledger)) {
                throw new \Exception(trans('public.message.not_found'));
            }

            $this->assertFinanceRetryable($ledger);
            if (! $this->withdrawalRetryService->isExhaustedNotSubmittedFailure($ledger)) {
                throw new \Exception(trans('public.message.final_status'));
            }
            if ($this->fundingApplyRepository->resetFailedWithdrawalForRiskRetry($orderNo) !== 1) {
                throw new \Exception(trans('public.message.data_error'));
            }

            return [
                'ledger' => $ledger,
                'apply' => FundingFacade::approveRisk($orderNo, $auditedBy),
            ];
        });

        return $this->dispatchApprovedWithdrawal($orderNo, $auditedBy, $approval);
    }

    private function dispatchApprovedWithdrawal(int $orderNo, int $auditedBy, array $approval): array
    {
        $prepared = $this->withdrawalRetryService->prepareInitialDispatch($orderNo);

        try {
            $withdrawalResult = $this->dispatchWithdrawal($approval['ledger'], $prepared['request_id']);
        } catch (FluxWalletException $e) {
            if ($this->withdrawalRetryService->isNotSubmittedError($e)) {
                $result = $this->buildAuditResult($approval['apply']);
                $retry = $this->withdrawalRetryService->recordInitialRetryFailure(
                    $orderNo,
                    $e->getErrorCode(),
                    $e->getMessage()
                );

                if (($retry['retry_phase'] ?? '') === FundingApply::WALLET_RETRY_PHASE_EXHAUSTED) {
                    $this->failFinanceAfterRetryExhausted($orderNo, $auditedBy, $retry, $e->getMessage());
                }

                $result['retry'] = $retry;

                return $result;
            }

            FundingFacade::failFinance($orderNo, $auditedBy, $e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
            FundingFacade::failFinance($orderNo, $auditedBy, $e->getMessage());
            throw $e;
        }

        if ($withdrawalResult !== [] && $this->withdrawalRetryService->isNotSubmittedResult($withdrawalResult)) {
            $result = $this->buildAuditResult($approval['apply']);
            $retry = $this->withdrawalRetryService->recordInitialRetryFailure(
                $orderNo,
                FundingApply::WALLET_STATUS_NOT_SUBMITTED,
                (string) ($withdrawalResult['status'] ?? '')
            );

            if (($retry['retry_phase'] ?? '') === FundingApply::WALLET_RETRY_PHASE_EXHAUSTED) {
                $this->failFinanceAfterRetryExhausted(
                    $orderNo,
                    $auditedBy,
                    $retry,
                    (string) ($withdrawalResult['status'] ?? '')
                );
            }

            $result['retry'] = $retry;

            return $result;
        }

        $this->withdrawalRetryService->recordSubmitted($orderNo);

        $result = $this->buildAuditResult($approval['apply']);
        if ($withdrawalResult !== []) {
            $result['withdrawal'] = $withdrawalResult;
        }

        return $result;
    }

    /**
     * 财务重试统一入口：重试窗口内的处理中单立即重试；已耗尽的失败单重置后重新发起钱包出金。
     */
    public function manualFinanceRetry(int $orderNo, int $auditedBy): array
    {
        $ledger = $this->getWithdrawalByOrderNoOrFail($orderNo);

        $isFailedOrder = (
            (int) ($ledger['status'] ?? -1) === FundingApplyStatus::Failed->value
            && (int) ($ledger['risk_status'] ?? -1) === FundingRiskStatus::Approved->value
            && (int) ($ledger['finance_status'] ?? 0) === FundingFinanceStatus::Failed->value
        );

        if ($isFailedOrder) {
            return $this->retryFailedWithdrawal($orderNo, $auditedBy);
        }

        $result = $this->attemptWithdrawalRetry($orderNo, FundingApply::WALLET_RETRY_TRIGGER_MANUAL, $auditedBy);

        return $result ?? throw new \Exception(trans('public.message.final_status'));
    }

    /**
     * 执行一次钱包出金重试：自动调度与手动按钮共用，状态机与幂等由 WithdrawalRetryService 保证。
     *
     * @return array<string, mixed>|null null 表示当前不可重试（未到期或已被接管）
     */
    public function attemptWithdrawalRetry(int $orderNo, string $trigger, int $auditedBy): ?array
    {
        $prepared = $this->withdrawalRetryService->prepareRetryAttempt($orderNo, $trigger);
        if ($prepared === null) {
            return null;
        }

        try {
            $withdrawalResult = $this->dispatchWithdrawal($prepared['ledger'], $prepared['request_id']);
        } catch (FluxWalletException $e) {
            return $this->resolveRetryAttemptFailure($orderNo, $trigger, $auditedBy, $e->getErrorCode(), $e->getMessage());
        }

        if ($withdrawalResult !== [] && $this->withdrawalRetryService->isNotSubmittedResult($withdrawalResult)) {
            return $this->resolveRetryAttemptFailure(
                $orderNo,
                $trigger,
                $auditedBy,
                FundingApply::WALLET_STATUS_NOT_SUBMITTED,
                (string) ($withdrawalResult['status'] ?? '')
            );
        }

        $this->withdrawalRetryService->recordSubmitted($orderNo);

        return [
            'order_no' => (string) $orderNo,
            'withdrawal' => $withdrawalResult,
            'retry' => $this->getRetryPayloadByOrderNo($orderNo),
        ];
    }

    /**
     * 自动重试调度入口：扫描重试窗口内到期订单并逐单执行。
     *
     * @return array{scanned:int, retried:int, skipped:int, exhausted:int, failed:int}
     */
    public function retryDueWithdrawals(): array
    {
        $summary = ['scanned' => 0, 'retried' => 0, 'skipped' => 0, 'exhausted' => 0, 'failed' => 0];
        $candidates = $this->withdrawalRetryService->getDueRetryCandidates(
            CarbonImmutable::now('UTC'),
            FundingApply::AUTO_RETRY_BATCH_SIZE
        );

        foreach ($candidates as $candidate) {
            $summary['scanned']++;
            $orderNo = (int) ($candidate['order_no'] ?? 0);

            try {
                $result = $this->attemptWithdrawalRetry(
                    $orderNo,
                    FundingApply::WALLET_RETRY_TRIGGER_AUTO,
                    FundingApply::SYSTEM_AUDITOR_ID
                );

                if ($result === null) {
                    $summary['skipped']++;
                } elseif (($result['retry']['retry_phase'] ?? '') === FundingApply::WALLET_RETRY_PHASE_EXHAUSTED) {
                    $summary['exhausted']++;
                } else {
                    $summary['retried']++;
                }
            } catch (\Throwable $e) {
                $summary['failed']++;
                Log::error('Automatic wallet withdrawal retry failed.', [
                    'order_no' => $orderNo,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    private function resolveRetryAttemptFailure(
        int $orderNo,
        string $trigger,
        int $auditedBy,
        string $errorCode,
        string $message
    ): array {
        if (! $this->withdrawalRetryService->isNotSubmittedCode($errorCode)) {
            $this->withdrawalRetryService->cancel($orderNo);
            FundingFacade::failFinance($orderNo, $auditedBy, $message);
            Log::error('Wallet withdrawal retry hit non-retryable wallet error.', [
                'order_no' => $orderNo,
                'trigger' => $trigger,
                'error_code' => $errorCode,
                'message' => $message,
            ]);

            throw new \Exception($message);
        }

        $retry = $this->withdrawalRetryService->recordRetryAttemptFailure($orderNo, $trigger, $errorCode, $message);

        if (($retry['retry_phase'] ?? '') === FundingApply::WALLET_RETRY_PHASE_EXHAUSTED) {
            $this->failFinanceAfterRetryExhausted($orderNo, $auditedBy, $retry, $message);
        }

        return [
            'order_no' => (string) $orderNo,
            'retry' => $retry,
        ];
    }

    private function getRetryPayloadByOrderNo(int $orderNo): array
    {
        $ledger = $this->getWithdrawalByOrderNoOrFail($orderNo);

        return $this->withdrawalRetryService->formatRetryPayload(
            $this->withdrawalRetryService->parseWalletDispatch($ledger)
        );
    }

    private function failFinanceAfterRetryExhausted(int $orderNo, int $auditedBy, array $retry, string $message): void
    {
        $errorCode = (string) ($retry['last_error_code'] ?? FundingApply::WALLET_STATUS_NOT_SUBMITTED);
        $reason = sprintf('%s: automatic retry exhausted (%d attempts)', $errorCode, FundingApply::MAX_AUTO_RETRY_COUNT);

        FundingFacade::failFinance($orderNo, $auditedBy, $reason);
        Log::error('Wallet withdrawal automatic retries exhausted.', [
            'order_no' => $orderNo,
            'request_id' => $retry['request_id'] ?? '',
            'attempts' => $retry['auto_retry_count'] ?? 0,
            'last_error_code' => $errorCode,
            'message' => $message,
        ]);
    }

    /**
     * 风控审核驳回
     */
    public function rejectRisk(int $orderNo, int $auditedBy, string $reason): array
    {
        $ledger = $this->getWithdrawalByOrderNoOrFail($orderNo);
        $this->assertRiskAuditable($ledger);

        $apply = FundingFacade::rejectRisk($orderNo, $auditedBy, $reason);

        return $this->buildAuditResult($apply);
    }

    /**
     * 财务审核驳回
     */
    public function rejectFinance(int $orderNo, int $auditedBy, string $reason): array
    {
        $apply = FundingFacade::rejectFinance($orderNo, $auditedBy, $reason);

        return $this->buildAuditResult($apply);
    }

    /**
     * 财务待处理/失败单：人工确认 hash 后直接通过。
     */
    public function retryFailedFinance(int $orderNo, int $auditedBy, string $hash, ?string $platform = null): array
    {
        $ledger = $this->getWithdrawalByOrderNoOrFail($orderNo);
        $this->assertFinanceHandleable($ledger);

        $referenceContent = $this->buildManualFinanceReferenceContent($ledger, $hash);

        [$payAmount, $payRate] = $this->resolvePayAmountAndRate($ledger);

        $apply = FundingFacade::approveFinance(
            orderNo: $orderNo,
            auditedBy: $auditedBy,
            platform: $platform,
            orderId: $hash,
            payAmount: $payAmount,
            payRate: $payRate,
            referenceContent: $referenceContent
        );

        return $this->buildAuditResult($apply);
    }

    private function buildAuditResult(mixed $apply): array
    {
        $orderNo = (string) ($apply->order_no ?? '');
        $statusCode = (int) ($apply->status ?? 0);
        $riskStatus = $this->toIntOrDefault($apply->risk_status ?? null, 0);
        $financeStatus = $this->toIntOrDefault($apply->finance_status ?? null, 0);

        return [
            'order_no' => $orderNo,
            'order_id' => (string) ($apply->order_id ?? ''),
            'effective_at' => $this->toDatetimeString($apply->effective_at ?? null),
            'status' => [
                'code' => $statusCode,
                'text' => $this->resolveApplyStatusText($statusCode),
                'risk_status' => $riskStatus,
                'finance_status' => $financeStatus,
            ],
        ];
    }

    private function getWithdrawalByOrderNoOrFail(int $orderNo): array
    {
        $ledger = $this->fundingApplyRepository->findWithdrawalByOrderNo($orderNo);
        if (empty($ledger)) {
            throw new \Exception(trans('public.message.not_found'));
        }

        return $ledger;
    }

    private function assertRiskAuditable(array $ledger): void
    {
        $status = (int) ($ledger['status'] ?? -1);
        if ($status === FundingApplyStatus::Pending->value) {
            return;
        }

        $riskStatus = (int) ($ledger['risk_status'] ?? -1);
        $financeStatus = (int) ($ledger['finance_status'] ?? 0);
        if (
            $status === FundingApplyStatus::Failed->value
            && $riskStatus === FundingRiskStatus::Approved->value
            && $financeStatus === FundingFinanceStatus::Failed->value
        ) {
            throw new \Exception(trans('withdrawal.message.risk_already_approved_finance_failed'));
        }

        throw new \Exception(trans('public.message.final_status'));
    }

    private function assertFinanceHandleable(array $ledger): void
    {
        $status = (int) ($ledger['status'] ?? -1);
        $riskStatus = (int) ($ledger['risk_status'] ?? -1);
        $financeStatus = (int) ($ledger['finance_status'] ?? 0);

        if (
            $status === FundingApplyStatus::Processing->value
            && $riskStatus === FundingRiskStatus::Approved->value
            && $financeStatus === FundingFinanceStatus::Pending->value
        ) {
            return;
        }

        if (
            $status === FundingApplyStatus::Failed->value
            && $riskStatus === FundingRiskStatus::Approved->value
            && $financeStatus === FundingFinanceStatus::Failed->value
        ) {
            return;
        }

        throw new \Exception(trans('public.message.final_status'));
    }

    private function assertFinanceRetryable(array $ledger): void
    {
        if (
            (int) ($ledger['status'] ?? -1) === FundingApplyStatus::Failed->value
            && (int) ($ledger['risk_status'] ?? -1) === FundingRiskStatus::Approved->value
            && (int) ($ledger['finance_status'] ?? 0) === FundingFinanceStatus::Failed->value
        ) {
            return;
        }

        throw new \Exception(trans('public.message.final_status'));
    }

    private function dispatchWithdrawal(array $ledger, string $requestId): array
    {
        $payload = $this->buildWithdrawalPayload($ledger);

        return FluxWallet::createWithdrawal(
            $payload['order_no'],
            $payload['chain_id'],
            $payload['symbol'],
            $payload['amount'],
            $payload['to_address'],
            $requestId,
        ) ?? [];
    }

    private function buildWithdrawalPayload(array $ledger): array
    {
        $withdrawTo = $this->normalizeJsonField($ledger['withdraw_to'] ?? null);
        $referenceContent = $this->normalizeJsonField($ledger['reference_content'] ?? null);
        $meta = array_merge($withdrawTo, $referenceContent);

        $orderNo = (string) ($ledger['order_no'] ?? '');
        $chainId = $this->pickString($meta, ['chain_id', 'chainId', 'network']);
        $toAddress = $this->pickString($meta, ['wallet_address', 'to_address', 'toAddress', 'to', 'address']);
        $symbol = $this->pickString($meta, ['token', 'symbol', 'currency', 'pay_currency', 'payCurrency'])
            ?? $this->pickString($ledger, ['pay_currency', 'currency'])
            ?? Money::DEFAULT_CURRENCY;
        $amount = $this->computeWithdrawalAmount($ledger['net_amount'] ?? null, $ledger['rate'] ?? null);

        if ($orderNo === '' || $chainId === null || $toAddress === null || $symbol === '') {
            throw new \Exception(trans('withdrawal.message.param_error'));
        }

        return [
            'order_no' => $orderNo,
            'chain_id' => $chainId,
            'symbol' => $symbol,
            'amount' => $amount,
            'to_address' => $toAddress,
        ];
    }

    /**
     * 人工确认 hash 后补齐一份接近自动回调格式的出金回执。
     *
     * @return array<string, mixed>
     */
    private function buildManualFinanceReferenceContent(array $ledger, string $hash): array
    {
        $withdrawTo = $this->normalizeJsonField($ledger['withdraw_to'] ?? null);
        $referenceContent = $this->normalizeJsonField($ledger['reference_content'] ?? null);
        $meta = array_merge($withdrawTo, $referenceContent);

        $payload = $this->buildWithdrawalPayload($ledger);

        $fromAddress = $this->pickString($meta, ['from_address', 'from']) ?? '';
        $requestId = $this->pickString($meta, ['request_id', 'requestId']) ?? '';

        $content = array_merge($meta, [
            'event' => 'WITHDRAWN',
            'token' => $payload['symbol'],
            'amount' => $payload['amount'],
            'source' => $this->pickString($meta, ['source']) ?? FluxWallet::OnChainSource,
            'status' => 'Completed',
            'version' => '1',
            'chain_id' => $payload['chain_id'],
            'timestamp' => Carbon::now('UTC')->format('Y-m-d\TH:i:s\Z'),
            'request_id' => $requestId,
            'to_address' => $payload['to_address'],
            'manual_transaction_hash' => $hash,
            'hash_source' => 'manual',
        ]);

        if ($fromAddress !== '') {
            $content['from_address'] = $fromAddress;
        }

        $blockNumber = $meta['block_number'] ?? $meta['blockNumber'] ?? null;
        if ($blockNumber !== null && $blockNumber !== '') {
            $content['block_number'] = is_numeric($blockNumber) ? (int) $blockNumber : $blockNumber;
        }

        return $content;
    }

    private function computeWithdrawalAmount(mixed $netAmount, mixed $rate): string
    {
        if (!is_numeric($netAmount) || !is_numeric($rate)) {
            throw new \Exception(trans('withdrawal.message.param_error'));
        }

        $amount = Calc::bcMul($netAmount, $rate, Money::AMOUNT_DECIMAL_PLACES);

        return (string) $amount;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function resolvePayAmountAndRate(array $ledger): array
    {
        $netAmount = $ledger['net_amount'] ?? null;
        $rate = $ledger['rate'] ?? null;

        if (!is_numeric($netAmount) || !is_numeric($rate)) {
            return [null, null];
        }

        return [$this->computeWithdrawalAmount($netAmount, $rate), (string) $rate];
    }

    /**
     * @return array{
     *     id: int,
     *     user_code: int,
     *     from: string,
     *     to: string,
     *     order_no: string,
     *     third_no: string,
     *     deposit_type: int,
     *     deposit_type_text: string,
     *     platform: string,
     *     platform_text: string,
     *     channel_text: string,
     *     currency: string,
     *     apply_amount: float,
     *     fee_amount: float,
     *     actual_currency: string,
     *     actual_amount: float,
     *     pay_currency: ?string,
     *     pay_amount: ?float,
     *     rate: ?float,
     *     received_at: ?string,
     *     arrival_time: ?string,
     *     tx_hash: string,
     *     network: string,
     *     network_text: string,
     *     email: string,
     *     type: int,
     *     type_text: string,
     *     operator_name: string,
     *     is_white_list_customer: bool,
     *     can_fail_finance: int,
     *     can_handle_finance: int,
     *     deposit_status_text: string,
     *     status: array{
     *         code: int,
     *         text: string,
     *         risk_status: int,
     *         finance_status: int
     *     },
     *     status_text: string,
     *     risk_status_text: string,
     *     finance_status_text: string,
     *     risk_reason: string,
     *     finance_reason: string,
     *     daily_withdraw_count?: int,
     *     daily_total_withdraw_amount?: float,
     *     withdrawal_time: ?string
     * }
     */
    private function formatCommonRow(
        array $record,
        bool $isFinanceList = false,
        array $riskOperatorNames = [],
        array $financeOperatorNames = [],
        bool $isDepositList = false
    ): array
    {
        $platform  = (string) ($record['platform'] ?? '');
        $fundType  = (int) ($record['fund_type'] ?? 0);
        $direction = (int) ($record['direction'] ?? FundingApply::DIRECTION_DEPOSIT);
        $userCode  = (int) ($record['user_code'] ?? 0);
        $userType  = (int) ($record['user_type'] ?? 0);
        $riskAuditedBy = (int) ($record['risk_audited_by'] ?? 0);
        $financeAuditedBy = (int) ($record['finance_audited_by'] ?? 0);

        $referenceContent = $this->normalizeJsonField($record['reference_content'] ?? null);
        $withdrawTo       = $this->normalizeJsonField($record['withdraw_to'] ?? null);
        $storedStatusCode = (int) ($record['status'] ?? 0);
        $statusCode       = $isDepositList
            ? $this->resolveApplyDisplayStatusCode($storedStatusCode, $userCode)
            : $storedStatusCode;
        $riskStatus       = $this->toIntOrDefault($record['risk_status'] ?? null, 0);
        $financeStatus    = $this->toIntOrDefault($record['finance_status'] ?? null, 0);
        $canHandleFinance = $this->canHandleFinance($storedStatusCode, $riskStatus, $financeStatus);
        $txHash           = $this->firstNonEmptyString([
            $referenceContent['manual_transaction_hash'] ?? null,
            $referenceContent['transaction_hash'] ?? null,
        ], '');
        $network          = $this->resolveNetwork($referenceContent);
        $networkText      = $this->resolveNetworkText(
            $this->resolveNetworkChainId($referenceContent, $withdrawTo),
            $this->resolveNetworkToken($referenceContent, $withdrawTo, $record)
        );
        $statusText       = $this->resolveApplyStatusText($statusCode);

        $row = [
            'id' => (int) ($record['id'] ?? 0),
            'user_code' => $userCode,
            'from' => $this->resolveFrom($record),
            'to' => $this->resolveTo($record),
            'order_no' => (string) ($record['order_no'] ?? ''),
            'third_no' => (string) ($record['order_id'] ?? ''),
            'deposit_type' => $isDepositList ? $direction : $fundType,
            'deposit_type_text' => $isDepositList ? trans('deposit.direction.' . $direction) : $this->resolveFundTypeText($fundType),
            'platform' => $platform,
            'platform_text' => $platform,
            'channel_text' => $platform,
            'currency' => (string) ($record['currency'] ?? Money::DEFAULT_CURRENCY),
            'apply_amount' => (float) ($record['apply_amount'] ?? 0),
            'fee_amount' => (float) ($record['fee_amount'] ?? 0),
            'actual_currency' => (string) ($record['pay_currency'] ?? $record['currency'] ?? Money::DEFAULT_CURRENCY),
            'actual_amount' => (float) ($record['net_amount'] ?? 0),
            'pay_currency' => $record['pay_currency'] ?? null,
            'pay_amount' => $record['pay_amount'] === null ? null : (float) $record['pay_amount'],
            'rate' => $record['pay_rate'] === null ? null : (float) $record['pay_rate'],
            'received_at' => $this->toDatetimeString($record['effective_at'] ?? null),
            'arrival_time' => $this->toDatetimeString($record['effective_at'] ?? null),
            'tx_hash' => $txHash,
            'network' => $network,
            'email' => (string) ($record['email'] ?? ''),
            'type' => $userType,
            'type_text' => trans('user.user_type.' . $userType),
            'operator_name' => $isFinanceList
                ? ((string) ($financeOperatorNames[$financeAuditedBy] ?? '') !== '' ? (string) ($financeOperatorNames[$financeAuditedBy] ?? '') : trans('fund_flow.actor.system'))
                : ((string) ($riskOperatorNames[$riskAuditedBy] ?? '') !== '' ? (string) ($riskOperatorNames[$riskAuditedBy] ?? '') : ' - '),
            'is_white_list_customer' => $userType === User::TYPE_TEST,
            'can_fail_finance' => $canHandleFinance,
            'can_handle_finance' => $canHandleFinance,
            'deposit_status_text' => $this->resolveDepositStatus($statusCode, $record, $isDepositList),
            'status' => [
                'code' => $statusCode,
                'text' => $statusText,
                'risk_status' => $riskStatus,
                'finance_status' => $financeStatus,
            ],
            'status_text' => $statusText,
            'risk_status_text' => trans('withdrawal.risk_status.' . $this->resolveRiskStatusCode($riskStatus)),
            'finance_status_text' => trans('withdrawal.finance_status.' . $this->resolveFinanceStatusCode($financeStatus)),
            'risk_reason' => (string) ($record['risk_reason'] ?? ''),
            'finance_reason' => (string) ($record['finance_reason'] ?? ''),
            'withdrawal_time' => $this->toDatetimeString($record['created_at'] ?? null),
        ];

        $row['network_text'] = $networkText;

        if (! $isFinanceList && ! $isDepositList) {
            $row['daily_withdraw_count'] = (int) ($record['daily_withdraw_count'] ?? 0);
            $row['daily_total_withdraw_amount'] = (float) ($record['daily_total_withdraw_amount'] ?? 0);
        }

        if ($isFinanceList) {
            $row['retry'] = $this->withdrawalRetryService->formatRetryPayload(
                $this->withdrawalRetryService->parseWalletDispatch($record)
            );
            $row['can_manual_retry'] = (int) (
                $canHandleFinance
                && $row['retry']['retryable']
                && ! $row['retry']['retry_in_progress']
                && in_array($row['retry']['retry_phase'], [
                    FundingApply::WALLET_RETRY_PHASE_RETRYING,
                    FundingApply::WALLET_RETRY_PHASE_EXHAUSTED,
                ], true)
            );
        }

        return $row;
    }

    private function canHandleFinance(int $status, int $riskStatus, int $financeStatus): int
    {
        return (int) (
            $riskStatus === FundingRiskStatus::Approved->value
            && (
                (
                    $status === FundingApplyStatus::Processing->value
                    && $financeStatus === FundingFinanceStatus::Pending->value
                )
                || (
                    $status === FundingApplyStatus::Failed->value
                    && $financeStatus === FundingFinanceStatus::Failed->value
                )
            )
        );
    }

    private function resolveRiskStatusFilterValue(int|string|null $status): ?int
    {
        if ($status === null || $status === '') {
            return null;
        }

        $map = FundingApply::withdrawalRiskStatusMap();

        if (is_numeric($status)) {
            $statusValue = (int) $status;

            return in_array($statusValue, array_values($map), true) ? $statusValue : null;
        }

        $statusCode = strtolower((string) $status);

        return $map[$statusCode] ?? null;
    }

    private function resolveFinanceStatusFilterValue(int|string|null $status): ?int
    {
        if ($status === null || $status === '') {
            return null;
        }

        $map = FundingApply::withdrawalFinanceStatusMap();

        if (is_numeric($status)) {
            $statusValue = (int) $status;

            return in_array($statusValue, array_values($map), true) ? $statusValue : null;
        }

        $statusCode = strtolower((string) $status);

        return $map[$statusCode] ?? null;
    }

    private function resolveRiskStatusCode(int $status): string
    {
        $map = array_flip(FundingApply::withdrawalRiskStatusMap());

        return $map[$status] ?? (string) $status;
    }

    private function resolveFinanceStatusCode(int $status): string
    {
        $map = array_flip(FundingApply::withdrawalFinanceStatusMap());

        return $map[$status] ?? (string) $status;
    }

    private function resolveFundTypeText(int $fundType): string
    {
        $type = TypeLedger::tryFrom($fundType);
        if ($type !== null) {
            return $type->label();
        }

        return (string) $fundType;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function resolveFrom(array $record): string
    {
        [$from] = $this->resolveFromToByFundFlowType($record);

        return $from;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function resolveTo(array $record): string
    {
        [, $to] = $this->resolveFromToByFundFlowType($record);

        return $to;
    }

    /**
     * 出入金列表来源/去向展示口径与资金流水保持一致。
     *
     * @param array<string, mixed> $record
     * @return array{0:string,1:string}
     */
    private function resolveFromToByFundFlowType(array $record): array
    {
        $funding = 'Funding';
        $placeholder = '-';
        $fundType = (int) ($record['fund_type'] ?? 0);
        $referenceContent = $this->normalizeJsonField($record['reference_content'] ?? null);
        $withdrawTo = $this->normalizeJsonField($record['withdraw_to'] ?? null);

        if ($fundType === TypeLedger::Deposit->value) {
            $walletAddress = $this->firstNonEmptyString([
                $referenceContent['from_address'] ?? null,
                $referenceContent['to_address'] ?? null,
            ], $placeholder);

            return [$walletAddress, $funding];
        }

        if ($fundType === TypeLedger::Withdraw->value) {
            $walletAddress = $withdrawTo['wallet_address'] ?? $placeholder;
            return [$funding, $walletAddress];
        }
        return [$placeholder, $placeholder];
    }

    private function resolveDepositStatus(int $statusCode, array $record, bool $isDepositList = false): string
    {
        if (
            (! $isDepositList || (int) ($record['direction'] ?? FundingApply::DIRECTION_DEPOSIT) === FundingApply::DIRECTION_DEPOSIT)
            && $statusCode === FundingApplyStatus::Success->value
        ) {
            $successfulDepositCount = (int) ($record['successful_deposit_count'] ?? 0);

            return $successfulDepositCount <= 1
                ? trans('deposit.deposit_status.first_time')
                : trans('deposit.deposit_status.multiple');
        }

        return $this->resolveApplyStatusText($statusCode);
    }

    private function resolveApplyStatusText(int $status): string
    {
        $statusEnum = FundingApplyStatus::tryFrom($status);
        if ($statusEnum !== null) {
            return $statusEnum->label();
        }

        $text = trans('deposit.status.' . $status);
        if ($text !== 'deposit.status.' . $status) {
            return $text;
        }

        return (string) $status;
    }

    private function resolveApplyDisplayStatusCode(int $status, int $userCode): int
    {
        return $userCode === 0 ? FundingApply::STATUS_FAILED : $status;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function pickString(array $meta, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $meta[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function toIntOrDefault(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    private function resolveNetwork(array $referenceContent): string
    {
        $chainId = $this->pickString($referenceContent, ['chain_id', 'chainId']);
        if ($chainId === null) {
            return '';
        }

        $text = trans('type_chain.' . $chainId);
        if ($text !== 'type_chain.' . $chainId) {
            return $text;
        }

        return $chainId;
    }

    private function resolveNetworkChainId(array $referenceContent, array $withdrawTo): string
    {
        return $this->pickString(
            array_merge($referenceContent, $withdrawTo),
            ['chain_id', 'chainId'],
        ) ?? '';
    }

    private function resolveNetworkToken(array $referenceContent, array $withdrawTo, array $record): string
    {
        return $this->pickString(
            array_merge($referenceContent, $withdrawTo),
            ['token', 'symbol'],
        ) ?? (string) ($record['currency'] ?? $record['pay_currency'] ?? '');
    }

    private function resolveNetworkText(string $chainId, string $tokenSymbol): string
    {
        if ($chainId === '') {
            return '';
        }

        try {
            return $this->fundingNetworkConfigService->networkDisplayText($chainId, $tokenSymbol);
        } catch (\Throwable) {
            return $this->resolveNetwork(['chain_id' => $chainId]);
        }
    }

    private function toDatetimeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value)->format('Y-m-d H:i:s');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, mixed> $values
     */
    private function firstNonEmptyString(array $values, string $fallback): string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }
}
