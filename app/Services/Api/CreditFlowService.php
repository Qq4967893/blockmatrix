<?php

namespace App\Services\Api;

use App\Repositories\Api\UserCreditLedgerRepository;
use App\Repositories\Api\UserCreditRecordRepository;
use Carbon\Carbon;
use Scaffold\Framework\Enums\TypeCreditLedger;
use Scaffold\Framework\Models\Api\UserCreditLedger;
use Scaffold\Framework\Models\Api\UserCreditRecord;

class CreditFlowService
{
    /**
     * 展示为支出方向（负数金额）的流水类型；解锁会将赠金转出，同样按支出展示。
     */
    private const array DECREMENT_FUND_TYPES = [
        TypeCreditLedger::ExpireDeduct->value,
        TypeCreditLedger::ManualClear->value,
        TypeCreditLedger::TradeLoss->value,
        TypeCreditLedger::Unlock->value,
    ];

    public function __construct(
        private UserCreditLedgerRepository $userCreditLedgerRepo,
        private UserCreditRecordRepository $userCreditRecordRepo,
    ) {}

    /**
     * @param  array{trade_ticket?:?string,created_start?:?string,created_end?:?string,fund_type?:int|string|null,credit_type?:int|string|null}  $filters
     * @return array{data: array<int, array{id:int,trade_ticket:string,fund_type:int,fund_type_text:string,credit_type:?int,credit_type_text:string,created_at:?string,amount:float,after_balance:float}>, total_count:int}
     */
    public function getList(int $userCode, array $filters, int $limit, int $offset): array
    {
        [$count, $records] = $this->userCreditLedgerRepo->paginateByUserCode($userCode, $filters, $limit, $offset);

        return [
            'data' => array_map(
                fn (UserCreditLedger $record): array => $this->formatRecord($record),
                $records
            ),
            'total_count' => $count,
        ];
    }

    /**
     * @return array{
     *     fund_type_options: array<int, array{code:int,label:string}>,
     *     credit_type_options: array<int, array{code:int,label:string}>,
     *     summary: array{total_bonus_amount:float,historical_unlocked_amount:float,current_bonus_amount:float,current_unlocked_amount:float}
     * }
     */
    public function options(int $userCode): array
    {
        return [
            'fund_type_options' => array_map(
                fn (TypeCreditLedger $type): array => [
                    'code' => $type->value,
                    'label' => $this->fundTypeLabel($type->value),
                ],
                TypeCreditLedger::cases()
            ),
            'credit_type_options' => array_map(
                fn (int $creditType): array => [
                    'code' => $creditType,
                    'label' => $this->creditTypeLabel($creditType),
                ],
                [UserCreditRecord::GENERAL, UserCreditRecord::RESTRICTED]
            ),
            'summary' => $this->userCreditRecordRepo->summarizeByUserCode($userCode),
        ];
    }

    private function formatRecord(UserCreditLedger $record): array
    {
        $fundType = (int) $record->type->value;
        $amount = (float) $record->amount;
        if (in_array($fundType, self::DECREMENT_FUND_TYPES, true)) {
            $amount = -abs($amount);
        }

        return [
            'id' => (int) $record->id,
            'trade_ticket' => (string) ($record->ticket ?? ''),
            'fund_type' => $fundType,
            'fund_type_text' => $this->fundTypeLabel($fundType),
            'credit_type' => $record->credit_type === null ? null : (int) $record->credit_type,
            'credit_type_text' => $this->creditTypeLabel($record->credit_type === null ? null : (int) $record->credit_type),
            'created_at' => $this->formatDateTime($record->created_at),
            'amount' => $amount,
            'after_balance' => (float) ($record->balance_after ?? 0),
        ];
    }

    private function creditTypeLabel(?int $creditType): string
    {
        if ($creditType === null) {
            return trans('credit_flow.credit_type.unknown');
        }

        $key = "credit_flow.credit_type.{$creditType}";
        $label = trans($key);

        return $label === $key ? trans('credit_flow.credit_type.unknown') : $label;
    }

    private function fundTypeLabel(int $fundType): string
    {
        $key = "credit_flow.type.{$fundType}";
        $label = trans($key);

        return $label === $key ? trans('credit_flow.type.unknown') : $label;
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->timezone('UTC')->format('Y-m-d H:i:s');
    }
}
