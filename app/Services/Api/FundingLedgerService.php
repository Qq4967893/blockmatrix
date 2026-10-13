<?php

namespace App\Services\Api;

use App\Models\Api\FundingOperation;
use App\Models\Api\FundingOperationRecord;
use App\Models\Api\FundingLedger;
use App\Repositories\Api\FundingLedgerRepository;
use App\Repositories\Api\FundingAccountRepository;
use App\Repositories\Api\TradeAccountRepository;
use App\Repositories\Api\FundingOperationRepository;
use App\Repositories\Api\FundingOperationRecordRepository;
use App\Models\Api\Money;
use App\Utils\Util;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Scaffold\Framework\Utils\Calc;
use Scaffold\Framework\Enums\TypeLedger;
use Scaffold\Funding\Facades\Funding;
use Scaffold\Framework\Models\Api\TradeAccount;

class FundingLedgerService
{
    private const OPERATION_TYPE_GRANT = 1;
    private const OPERATION_TYPE_DEDUCT = -1;

    public function __construct(
        private FundingLedgerRepository $fundingLedgerRepo,
        private FundingAccountRepository $fundingAccountRepo,
        private TradeAccountRepository $tradeAccountRepo,
        private FundingOperationRepository $fundingOperationRepo,
        private FundingOperationRecordRepository $fundingOperationRecordRepo,
    ){}

    /**
     * @return array<int, array{code:int, name:string}>
     */
    public function getTypeOptions(): array
    {
        return array_map(
            fn (TypeLedger $type) => ['code' => $type->value, 'name' => $type->label()],
            TypeLedger::cases()
        );
    }

    /**
     * @return array<int, array{code:int, name:string}>
     */
    public function getManagementTypeOptions(): array
    {
        $types = [
            TypeLedger::NegativeBalance,
            TypeLedger::Bonus,
            TypeLedger::RedPacket,
            TypeLedger::FundsRefund,
            TypeLedger::AssetTransfer,
        ];
        return array_map(
            fn ($type) => [
                'code' => $type->value,
                'name' => $type->label(),
            ],
            $types
        );
    }

    /**
     * @param array{
     *     user_code?: int|null,
     *     created_start?: string|null,
     *     created_end?: string|null,
     *     fund_type?: int|null
     * } $filters
     * @return array{data: array<int, array>, total_count: int}
     */
    public function getList(array $filters, int $limit, int $offset): array
    {
        $fundType = [];
        if (!empty($filters['fund_type'])) {
            $fundType = [$filters['fund_type']];
        }
        [$records, $count] = $this->fundingLedgerRepo->paginate(
            [
                'user_code'    => $filters['user_code'] ?? null,
                'created_start'=> $filters['created_start'] ?? null,
                'created_end'  => $filters['created_end'] ?? null,
                'fund_type'    => $fundType,
            ],
            $limit,
            $offset
        );

        $data = array_map(function (array $record): array {
            $fundType = (int) ($record['fund_type'] ?? 0);
            $direction = (int) ($record['direction'] ?? FundingLedger::DIRECTION_IN);
            $rawAmount = (float) ($record['net_amount'] ?? $record['apply_amount'] ?? 0);
            $amount = $direction === FundingLedger::DIRECTION_OUT ? -abs($rawAmount) : abs($rawAmount);

            [$from, $to] = $this->buildFromTo($direction, $record);

            return [
                'id'             => (int) ($record['id'] ?? 0),
                'user_code'      => (int) ($record['user_code'] ?? 0),
                'fund_type'      => $fundType,
                'fund_type_text' => TypeLedger::tryFrom($fundType)?->label() ?? '',
                'created_at'     => $this->formatDatetime($record['created_at'] ?? null),
                'from'           => $from,
                'to'             => $to,
                'amount'         => $amount,
                'after_balance'  => (float) ($record['after_balance'] ?? 0),
            ];
        }, $records);

        return [
            'data'        => $data,
            'total_count' => $count,
        ];
    }

    /**
     * 获取资金管理列表，并补充操作来源、方向、原因和操作人信息。
     *
     * @param array{
     *     user_code?: int|null,
     *     operation_source?: int|null,
     *     direction?: int|null,
     *     created_start?: string|null,
     *     created_end?: string|null,
     *     fund_type?: int|null
     * } $filters
     * @return array{data: array<int, array>, total_count: int}
     */
    public function getManagementList(array $filters, int $limit, int $offset){
        // 默认值
        $fundType = FundingLedger::managementTypeOptions();
        if (!empty($filters['fund_type'])) {
            $fundType = [$filters['fund_type']];
        }
        [$records, $count] = $this->fundingLedgerRepo->paginate(
            [
                'user_code'        => $filters['user_code'] ?? null,
                'operation_source' => $filters['operation_source'] ?? null,
                'direction'        => $filters['direction'] ?? null,
                'created_start'    => $filters['created_start'] ?? null,
                'created_end'      => $filters['created_end'] ?? null,
                'fund_type'        => $fundType,
            ],
            $limit,
            $offset
        );

        if (count($records) == 0) {
            return [
                'data'        => $records,
                'total_count' => $count,
            ];
        }
        $fundingLedgeIds = array_column($records, 'id');
        $fundingOperations = $this->fundingOperationRecordRepo->getAll($fundingLedgeIds);
        $fundingOperations = array_column($fundingOperations, null, 'funding_ledger_id');

        $data = array_map(function (array $record) use ($fundingOperations): array {
            $fundType = (int) ($record['fund_type'] ?? 0);
            $direction = (int) ($record['direction'] ?? FundingLedger::DIRECTION_IN);

            $payableDeductionAmount = 0;
            $reason = '';
            $operatorName = '';
            if (isset($fundingOperations[$record['id']])){
                $payableDeductionAmount = $fundingOperations[$record['id']]['amount'];
                $reason = $fundingOperations[$record['id']]['reason'];
                $operatorName = $fundingOperations[$record['id']]['login_name'];
            }

            return [
                'id'             => (int) ($record['id'] ?? 0),
                'user_code'      => (int) ($record['user_code'] ?? 0),
                'operation_source' => trans('funding_ledger.operation_source')[$record['operation_source']],
                'direction'       => trans('funding_ledger.direction')[$direction],
                'fund_type'      => $fundType,
                'fund_type_text' => TypeLedger::tryFrom($fundType)?->label() ?? '',
                'remark'         => $record['remark'] ?? '',
                'reason'          => $reason,
                'payable_deduction_amount' => Util::normalizeDecimalOutput($payableDeductionAmount),
                'amount' => Util::normalizeDecimalOutput($record['net_amount'] ?? 0),
                'operator_name'   => $operatorName,
                'created_at'     => $this->formatDatetime($record['created_at'] ?? null),
            ];
        }, $records);

        return [
            'data'        => $data,
            'total_count' => $count,
        ];
    }

    /**
     * 获取用户赠金汇总信息。
     *
     * 返回收入、支出和净额，并统一金额输出格式。
     *
     * @param int $userCode 用户ID
     *
     * @return array{in:int|string, out:int|string, net:int|string}
     */
    public function getBonusByUserCode(int $userCode){
        $bonusInfos = $this->fundingLedgerRepo->getBonusByUserCode($userCode);
        if (empty($bonusInfos)) {
            return [
                'in' => 0,
                'out' => 0,
                'net' => 0,
            ];
        }

        return [
            'in' => Util::normalizeDecimalOutput($bonusInfos[FundingLedger::DIRECTION_IN] ?? 0),
            'out' => Util::normalizeDecimalOutput($bonusInfos[FundingLedger::DIRECTION_OUT] ?? 0),
            'net' => Util::normalizeDecimalOutput(
                Calc::bcSub((string) ($bonusInfos[FundingLedger::DIRECTION_IN] ?? 0), (string) ($bonusInfos[FundingLedger::DIRECTION_OUT] ?? 0), 6)
            ),
        ];
    }

    /**
     * 处理资金管理新增请求，并返回批次处理结果。
     *
     * @param array{
     *     user_codes: array<int>,
     *     fund_type: int,
     *     direction: int,
     *     amount: int|float|string,
     *     activity_name?: string,
     *     reason?: string
     * } $filters
     * @return array{
     *     batch_id: string,
     *     total_count: int,
     *     success_count: int,
     *     failed_count: int,
     *     results: array<int, array<string, mixed>>
     * }
     */
    public function add(array $filters): array
    {
        return $this->manageFunds(
            $filters['user_codes'],
            $filters['fund_type'],
            $filters['direction'],
            $filters['amount'],
            $filters['activity_name'] ?? '',
            $filters['reason'] ?? ''
        );
    }

    /**
     * 为单个用户发放资金，并记录对应的后台操作日志。
     *
     * @return array{
     *     user_code:int,
     *     is_succ:bool,
     *     message:string,
     *     ledger_id:int,
     *     amount:string
     * }
     */
    public function transferFunds($userCode, $fundType, $amount, $remark, $reason, $fundingOperationRecordId = null): array
    {
        if (Calc::bcComp((string) $amount, 0, Money::AMOUNT_DECIMAL_PLACES) <= 0) {
            throw new \RuntimeException("用户 {$userCode} 的发放金额必须大于 0");
        }

        if (Calc::bcComp((string) $amount, Money::MAX_MANUAL_AMOUNT, Money::AMOUNT_DECIMAL_PLACES) >= 0) {
            throw new \RuntimeException("用户 {$userCode} 的发放金额必须小于 " . Money::MAX_MANUAL_AMOUNT);
        }

        // 判断真实交易账号是否存在
        $tradeAccountIds = $this->tradeAccountRepo->getTradeAccountIdsByUserCode($userCode);
        if (count($tradeAccountIds) < 1) {
            throw new \RuntimeException("用户 {$userCode} 的交易账户不存在");
        }

        try {
            $ledger = Funding::otherIn($userCode, TypeLedger::from($fundType), $amount, null, null, null, FundingLedger::OPERATION_SOURCE_MANUAL, $remark, false);
        } catch (\Throwable $e) {
            Log::error('Failed to grant funding ledger amount.', [
                'user_code' => $userCode,
                'fund_type' => $fundType,
                'amount' => $amount,
                'remark' => $remark,
            ]);
            throw new \RuntimeException("给用户 {$userCode} 发放资金失败: {$e->getMessage()}", 0, $e);
        }

        $fundingLedgerId = (int) $ledger->id;
        $this->recordFundingOperationResult(self::OPERATION_TYPE_GRANT, $fundType, $fundingLedgerId, $userCode, $amount, $reason, $remark, $fundingOperationRecordId);

        return [
            'user_code' => (int) $userCode,
            'is_succ' => true,
            'message' => trans('public.message.add_success'),
            'ledger_id' => $fundingLedgerId,
            'amount' => (string) $amount,
        ];
    }

    /**
     * 为单个用户扣除资金。
     *
     * 优先从 funding 账户扣除；若 funding 可用余额不足，则尝试从交易账号划转到 funding 后再继续扣除。
     *
     * @return array{
     *     user_code:int,
     *     is_succ:bool,
     *     message:string,
     *     ledger_id:int,
     *     amount:string
     * }
     */
    public function deductFunds($userCode, $fundType, $amount, $remark, $reason, $fundingOperationRecordId = null): array
    {
        if (Calc::bcComp((string) $amount, 0, Money::AMOUNT_DECIMAL_PLACES) <= 0) {
            throw new \RuntimeException("用户 {$userCode} 的扣除金额必须大于 0");
        }

        if (Calc::bcComp((string) $amount, Money::MAX_MANUAL_AMOUNT, Money::AMOUNT_DECIMAL_PLACES) >= 0) {
            throw new \RuntimeException("用户 {$userCode} 的扣除金额必须小于 " . Money::MAX_MANUAL_AMOUNT);
        }

        // 判断真实交易账号是否存在
        $tradeAccountIds = $this->tradeAccountRepo->getTradeAccountIdsByUserCode($userCode);
        if (count($tradeAccountIds) < 1) {
            throw new \RuntimeException("用户 {$userCode} 的交易账户不存在");
        }

        // 查看funding账号的金额是否足额，如果不足额查看交易账号内可用余额
        $fundingAccountInfo = $this->fundingAccountRepo->findByUserCode($userCode);
        if (empty($fundingAccountInfo)) {
            $availableBalance = 0;
        } else {
            $availableBalance = $fundingAccountInfo['available_balance'];
        }

        // 查看funding账号的金额是否足额
        if (Calc::bcComp((string) $availableBalance, (string) $amount, 6) < 0) {
            // 查看交易账号内可用余额
            $tradeAccountBalance = TradeAccount::availableBalance($userCode, $tradeAccountIds[0]);

            $totalAvailableBalance = Calc::bcAdd((string) $tradeAccountBalance, (string) $availableBalance, 6);
            // 判断（funding账号的金额 + 交易账号的余额） 是否足额,不足额全部转到资金账户扣除；足额转出（扣除金额 - $availableBalance）到资金账户后全部扣除
            if (Calc::bcComp($totalAvailableBalance, (string) $amount, 6) < 0) {
                $toFundingAmount = $tradeAccountBalance;
                $newAmount = $totalAvailableBalance;
            } else {
                $toFundingAmount = Calc::bcSub((string) $amount, (string) $availableBalance, 6);
                $newAmount = $amount;
            }

            // 判断交易账号内是否有余额，如果没有跳过
            if ($tradeAccountBalance > 0) {
                // 把交易账号内的余额转到funding账号内
                try {
                    Funding::transferTradeToFunding($userCode, $tradeAccountIds[0], $toFundingAmount, TypeLedger::Transfer, false);
                } catch (\Throwable $e) {
                    Log::error('Failed to transfer trade balance to funding.', [
                        'user_code' => $userCode,
                        'trade_account_id' => $tradeAccountIds[0],
                        'transfer_amount' => $toFundingAmount,
                    ]);

                    throw new \RuntimeException(
                        "给用户 {$userCode} {$tradeAccountIds[0]} 从交易账号划转到 funding 失败: {$e->getMessage()}",
                        0,
                        $e
                    );
                }
            }

            // 判断如果funding账号的金额 + 交易账号的余额 < 0证明该账户没有余额，直接扣除失败即可
            if ($newAmount <= 0) {
                throw new \RuntimeException("给用户 {$userCode} 扣除资金失败: 该用户已没有余额");
            }

            try {
                $ledger = Funding::otherOut($userCode, TypeLedger::from($fundType), $newAmount, null, null, FundingLedger::OPERATION_SOURCE_MANUAL, $remark, false);
            } catch (\Throwable $e) {
                Log::error('Failed to deduct funding ledger amount.', [
                    'user_code' => $userCode,
                    'fund_type' => $fundType,
                    'amount' => $newAmount,
                    'remark' => $remark,
                ]);

                throw new \RuntimeException(
                    "给用户 {$userCode} 扣除资金失败: {$e->getMessage()}",
                    0,
                    $e
                );
            }
        } else {
            try {
                $ledger = Funding::otherOut($userCode, TypeLedger::from($fundType), $amount, null, null, FundingLedger::OPERATION_SOURCE_MANUAL, $remark, false);
            } catch (\Throwable $e) {
                Log::error('Failed to deduct funding ledger amount.', [
                    'user_code' => $userCode,
                    'fund_type' => $fundType,
                    'amount' => $amount,
                    'remark' => $remark,
                ]);
                throw new \RuntimeException("给用户 {$userCode} 扣除资金失败: {$e->getMessage()}", 0, $e);
            }
        }

        $fundingLedgerId = (int) $ledger->id;
        $this->recordFundingOperationResult(self::OPERATION_TYPE_DEDUCT, $fundType, $fundingLedgerId, $userCode, $amount, $reason, $remark, $fundingOperationRecordId);

        return [
            'user_code' => (int) $userCode,
            'is_succ' => true,
            'message' => trans('public.message.add_success'),
            'ledger_id' => $fundingLedgerId,
            'amount' => (string) ($newAmount ?? $amount),
        ];
    }

    /**
     * 批量处理资金管理请求，按用户维度汇总成功与失败结果。
     *
     * 该方法不会因单个用户处理失败而中断整批，而是生成批次号并返回逐用户处理明细。
     *
     * @return array{
     *     batch_id:string,
     *     total_count:int,
     *     success_count:int,
     *     failed_count:int,
     *     results:array<int, array<string, mixed>>
     * }
     */
    public function manageFunds($userCodes, $fundType, $direction, $amount, $remark, $reason): array
    {
        $batchId = uniqid(FundingLedger::BATCH_ID_PREFIX, true);
        $results = [];

        if ($direction === FundingLedger::DIRECTION_IN) {
            foreach ($userCodes as $userCode) {
                try {
                    $results[] = $this->transferFunds($userCode, $fundType, $amount, $remark, $reason);
                } catch (\Throwable $e) {
                    $results[] = [
                        'user_code' => (int) $userCode,
                        'is_succ' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        } else {
            foreach ($userCodes as $userCode) {
                try {
                    $results[] = $this->deductFunds($userCode, $fundType, $amount, $remark, $reason);
                } catch (\Throwable $e) {
                    $results[] = [
                        'user_code' => (int) $userCode,
                        'is_succ' => false,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        }

        $successCount = count(array_filter($results, static fn (array $result) => (bool) ($result['is_succ'] ?? false)));
        $failedCount = count($results) - $successCount;

        return [
            'batch_id' => $batchId,
            'total_count' => count($results),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return array{0:string, 1:string}
     */
    private function buildFromTo(int $direction, array $record): array
    {
        $system      = (string) trans('fund_flow.actor.system');
        $funding     = 'Funding';
        $uta         = (string) trans('fund_flow.actor.uta');
        $placeholder = '-';
        $fundType    = (int) ($record['fund_type'] ?? 0);
        // fund_type=1：入金 钱包地址 -> Funding
        if ($fundType === TypeLedger::Deposit->value) {
            $referenceContent = $this->resolveApplyReferenceContent($record);

            return [(string) ($referenceContent['from_address'] ?? ''), $funding];
        }

        // fund_type=2：出金 Funding -> 钱包地址
        if ($fundType === TypeLedger::Withdraw->value) {
            $referenceContent = $this->resolveApplyReferenceContent($record);

            return [$funding, (string) ($referenceContent['to_address'] ?? '')];
        }

        // fund_type=10：划转 Funding <-> UTA（按方向）
        if ($fundType === TypeLedger::Transfer->value) {
            if ($direction === FundingLedger::DIRECTION_OUT) {
                return [$funding, $uta];
            }

            return [$uta, $funding];
        }

        // fund_type=8：资金退回 系统 -> Funding
        if ($fundType === TypeLedger::FundsRefund->value) {
            return [$system, $funding];
        }

        // fund_type=3：代理商分佣 系统 -> Funding
        if ($fundType === TypeLedger::IbCommission->value) {
            return [$system, $funding];
        }

        // 其他类型默认按照方向展示：发放=系统->Funding，扣除=Funding->系统
        if ($direction === FundingLedger::DIRECTION_OUT) {
            return [$funding, $system];
        }

        if ($direction === FundingLedger::DIRECTION_IN) {
            return [$system, $funding];
        }

        return [$placeholder, $placeholder];
    }

    /**
     * 记录资金操作结果。
     *
     * 批量执行场景下，回写已有明细记录的 funding_ledger_id；
     * 单条操作场景下，补写一条成功明细用于留痕。
     */
    private function recordFundingOperationResult(
        int $direction,
        int $fundType,
        int $fundingLedgerId,
        $userCode,
        $amount,
        string $reason,
        $remark,
        $fundingOperationRecordId = null
    ): void {
        if (! is_null($fundingOperationRecordId)) {
            $this->fundingOperationRecordRepo->update(['funding_ledger_id' => $fundingLedgerId], $fundingOperationRecordId, 'id');
            return;
        }

        $loginUser = Auth::user();
        $createdBy = (int) ($loginUser['id'] ?? 0);
        $operationId = $this->fundingOperationRepo->insertGetId([
            'direction' => $direction,
            'fund_type' => $fundType,
            'currency' => Money::DEFAULT_CURRENCY,
            'total_count' => 1,
            'valid_count' => 1,
            'success_count' => 1,
            'total_amount' => $amount,
            'success_amount' => $amount,
            'status' => FundingOperation::STATUS_SUCCESS,
            'created_by' => $createdBy,
        ]);

        $this->fundingOperationRecordRepo->insert([
            'job_id' => $operationId,
            'funding_ledger_id' => $fundingLedgerId,
            'user_code' => $userCode,
            'amount' => $amount,
            'reason' => $reason,
            'activity_name' => $remark,
            'status' => FundingOperationRecord::STATUS_SUCCESS,
        ]);
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function resolveApplyReferenceContent(array $record): array
    {
        $value = $record['apply_reference_content'] ?? null;
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 统一格式化日期时间字段。
     *
     * @param mixed $value 原始时间值
     *
     * @return string|null
     */
    private function formatDatetime(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format(Money::DATETIME_FORMAT);
        }

        if (is_string($value) && $value !== '') {
            return date(Money::DATETIME_FORMAT, strtotime($value));
        }

        return null;
    }

}
