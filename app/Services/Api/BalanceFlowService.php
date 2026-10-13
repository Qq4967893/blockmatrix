<?php

namespace App\Services\Api;

use App\Constants\BalanceFlowFeeCategory;
use App\Repositories\Api\BalanceFlowRepository;
use App\Repositories\Api\TradeAccountRepository;
use Carbon\CarbonInterface;
use Scaffold\Framework\Enums\TypeTradeAccount;
use Scaffold\Framework\Models\TradingSystem\BalanceFlow;

class BalanceFlowService
{
    private const MOCK_BONUS_BALANCE = 100.0;

    public function __construct(
        private BalanceFlowRepository $balanceFlowRepo,
        private TradeAccountRepository $tradeAccountRepo,
    ) {
    }

    /**
     * @return array<int, array{code:int, name:string, sub_types?: array<int, array{code:int, name:string}>}>
     */
    public function getTypeOptions(): array
    {
        return [
            [
                'code' => BalanceFlowFeeCategory::SETTLEMENT,
                'name' => $this->resolveFeeCategoryName(BalanceFlowFeeCategory::SETTLEMENT),
            ],
            $this->depositWithdrawalTypeOption(),
            [
                'code' => BalanceFlowFeeCategory::DIVIDEND,
                'name' => $this->resolveFeeCategoryName(BalanceFlowFeeCategory::DIVIDEND),
            ],
            [
                'code' => BalanceFlowFeeCategory::NEGATIVE_BALANCE_COMPENSATION,
                'name' => $this->resolveFeeCategoryName(BalanceFlowFeeCategory::NEGATIVE_BALANCE_COMPENSATION),
            ],
            [
                'code' => BalanceFlowFeeCategory::TRADING_FEE,
                'name' => $this->resolveFeeCategoryName(BalanceFlowFeeCategory::TRADING_FEE),
            ],
        ];
    }

    /**
     * @return array{code:int, name:string, sub_types: array<int, array{code:int, name:string}>}
     */
    private function depositWithdrawalTypeOption(): array
    {
        return [
            'code' => BalanceFlowFeeCategory::DEPOSIT_WITHDRAWAL,
            'name' => sprintf(
                '%s/%s',
                $this->resolveFeeCategoryName(BalanceFlowFeeCategory::DEPOSIT_WITHDRAWAL, 1),
                $this->resolveFeeCategoryName(BalanceFlowFeeCategory::DEPOSIT_WITHDRAWAL, -1),
            ),
            'sub_types' => [
                [
                    'code' => BalanceFlowFeeCategory::SUB_TYPE_UNLOCK_CREDIT,
                    'name' => $this->resolveFeeSubTypeName(BalanceFlowFeeCategory::SUB_TYPE_UNLOCK_CREDIT),
                ],
            ],
        ];
    }

    /**
     * @param array{
     *     trade_ticket?: string|null,
     *     created_start?: string|null,
     *     created_end?: string|null,
     *     fund_type?: int|null
     *     fund_sub_type?: int|null
     *     login?: int|string|null
     *     account_type?: int|null
     * } $filters
     * @return array{data: array<int, array>, total_count: int}
     */
    public function getList(int $userCode, array $filters, int $limit, int $offset): array
    {
        $logins = collect($this->tradeAccountRepo->getAccountsByUserCode($userCode))
            ->pluck('trade_account_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($logins)) {
            return [
                'data'        => [],
                'total_count' => 0,
            ];
        }

        [$records, $count] = $this->balanceFlowRepo->paginateByLogins(
            $logins,
            [
                'trade_ticket'  => $filters['trade_ticket'] ?? null,
                'created_start' => $filters['created_start'] ?? null,
                'created_end'   => $filters['created_end'] ?? null,
                'fund_type'     => $filters['fund_type'] ?? null,
                'fund_sub_type' => $filters['fund_sub_type'] ?? null,
                'login'         => $filters['login'] ?? null,
                'account_type'  => $filters['account_type'] ?? null,
            ],
            $limit,
            $offset
        );

        $data = array_map(function (array $record): array {
            $fundType = (int) ($record['fee_category'] ?? 0);
            $amount   = (float) ($record['amount'] ?? 0);

            return [
                'id'             => (int) ($record['id'] ?? 0),
                'trade_ticket'   => (string) ($record['ref_ticket'] ?? ''),
                'login'          => (int) ($record['login'] ?? 0),
                'account_type'   => (int) ($record['account_type'] ?? -1),
                'account_type_text' => $this->resolveAccountTypeName((int) ($record['account_type'] ?? -1)),
                'fund_type'      => $fundType,
                'fund_sub_type'  => (int) ($record['fee_category_sub'] ?? 0),
                'fund_type_text' => (string) ($record['fee_category_text'] ?? ''),
                'created_at'     => $this->formatDatetime($record['created_at'] ?? null),
                'amount'         => $amount,
                'balance'        => (float) ($record['balance'] ?? 0),
                'bonus_balance'  => self::MOCK_BONUS_BALANCE,
            ];
        }, $records);

        return [
            'data'        => $data,
            'total_count' => $count,
        ];
    }

    private function resolveFeeCategoryName(int $feeCategory, float $amount = 0): string
    {
        $flow = new BalanceFlow();
        $flow->setAttribute('fee_category', $feeCategory);
        $flow->setAttribute('amount', $amount);

        return $flow->getFeeCategoryTextAttribute();
    }

    private function resolveFeeSubTypeName(int $feeSubType): string
    {
        $flow = new BalanceFlow();
        $flow->setAttribute('fee_category', BalanceFlowFeeCategory::DEPOSIT_WITHDRAWAL);
        $flow->setAttribute('fee_category_sub', $feeSubType);

        return $flow->getFeeCategoryTextAttribute();
    }

    private function formatDatetime(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && $value !== '') {
            return date('Y-m-d H:i:s', strtotime($value));
        }

        return null;
    }

    private function resolveAccountTypeName(int $accountType): string
    {
        return TypeTradeAccount::tryFrom($accountType)?->label() ?? (string) $accountType;
    }
}
