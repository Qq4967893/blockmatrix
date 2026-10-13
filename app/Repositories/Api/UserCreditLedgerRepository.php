<?php

namespace App\Repositories\Api;

use Illuminate\Database\Eloquent\Builder;
use Scaffold\Framework\Enums\TypeCreditLedger;
use Scaffold\Framework\Models\Api\UserCreditLedger;
use Scaffold\Framework\Repositories\Repository;

class UserCreditLedgerRepository extends Repository
{
    public function __construct(UserCreditLedger $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{trade_ticket?:?string,created_start?:?string,created_end?:?string,fund_type?:int|string|null,credit_type?:int|string|null}  $filters
     * @return array{0: int, 1: array<int, UserCreditLedger>} 模型附带 credit_type 属性（关联 user_credit_record）
     */
    public function paginateByUserCode(int $userCode, array $filters, int $limit, int $offset): array
    {
        $query = $this->getQuery()
            ->leftJoin('user_credit_record', 'user_credit_record.id', '=', 'user_credit_ledger.user_credit_record_id')
            ->select('user_credit_ledger.*')
            ->addSelect('user_credit_record.credit_type')
            ->where('user_credit_ledger.user_code', $userCode);

        $this->applyFilters($query, $filters);

        $count = (clone $query)->count();
        $records = $query
            ->orderByDesc('user_credit_ledger.created_at')
            ->orderByDesc('user_credit_ledger.id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->all();

        return [$count, $records];
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $ticket = trim((string) ($filters['trade_ticket'] ?? ''));
        if ($ticket !== '') {
            $query->where('user_credit_ledger.ticket', $ticket);
        }

        if (! empty($filters['created_start'])) {
            $query->where('user_credit_ledger.created_at', '>=', (string) $filters['created_start']);
        }

        if (! empty($filters['created_end'])) {
            $query->where('user_credit_ledger.created_at', '<=', (string) $filters['created_end']);
        }

        if (isset($filters['fund_type']) && $filters['fund_type'] !== '') {
            $query->where('user_credit_ledger.type', (int) $filters['fund_type']);
        }

        if (isset($filters['credit_type']) && $filters['credit_type'] !== '') {
            $query->where('user_credit_record.credit_type', (int) $filters['credit_type']);
        }
    }

    /**
     * 按发放记录取流水，用于详情列表补充订单号与解锁累计。
     *
     * @param  array<int>  $creditRecordIds
     * @return array<int, UserCreditLedger>
     */
    public function listsByCreditRecordIds(array $creditRecordIds): array
    {
        if ($creditRecordIds === []) {
            return [];
        }

        return $this->getQuery()
            ->whereIn('user_credit_record_id', $creditRecordIds)
            ->orderBy('user_credit_record_id')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * 单条发放记录的解锁流水累计。
     */
    public function sumUnlockByCreditRecordId(int $creditRecordId): float
    {
        return (float) $this->getQuery()
            ->where('user_credit_record_id', $creditRecordId)
            ->where('type', TypeCreditLedger::Unlock->value)
            ->sum('amount');
    }
}
