<?php

namespace App\Repositories\Api;

use App\Constants\BalanceFlowFeeCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Models\TradingSystem\BalanceFlow;
use Scaffold\Framework\Repositories\Repository;

class BalanceFlowRepository extends Repository
{
    public function __construct(BalanceFlow $model)
    {
        parent::__construct($model);
    }

    /**
     * @param array<int> $logins
     * @param array{
     *     trade_ticket?: string|null,
     *     created_start?: string|null,
     *     created_end?: string|null,
     *     fund_type?: int|null
     *     fund_sub_type?: int|null
     *     login?: int|string|null
     *     account_type?: int|string|null
     * } $filters
     * @return array{0: array<int, array>, 1: int}
     */
    public function paginateByLogins(array $logins, array $filters, int $limit, int $offset): array
    {
        if (empty($logins)) {
            return [[], 0];
        }

        $query = $this->baseQuery()
            ->whereIn('bf.login', $logins)
            ->whereIn('bf.fee_category', BalanceFlowFeeCategory::VISIBLE)
            ->leftJoin(DB::raw(DB_API . '.trade_account as ta'), 'ta.trade_account_id', '=', 'bf.login');

        if (! empty($filters['trade_ticket'])) {
            $query->where('bf.ref_ticket', (string) $filters['trade_ticket']);
        }

        if (! empty($filters['created_start'])) {
            $query->where('bf.created_at', '>=', $filters['created_start']);
        }

        if (! empty($filters['created_end'])) {
            $query->where('bf.created_at', '<=', $filters['created_end']);
        }

        if (! empty($filters['fund_type'])) {
            $query->where('bf.fee_category', (int) $filters['fund_type']);
        }

        if (isset($filters['fund_sub_type']) && $filters['fund_sub_type'] !== '') {
            $query->where('bf.fee_category_sub', (int) $filters['fund_sub_type']);
        }

        if (! empty($filters['login'])) {
            $query->where('bf.login', (int) $filters['login']);
        }

        if (isset($filters['account_type']) && $filters['account_type'] !== '') {
            $query->where('ta.trade_account_type', (int) $filters['account_type']);
        }

        $count = (clone $query)->count('bf.id');

        $query->orderByDesc('bf.id');
        if ($offset !== -1 && $limit !== -1) {
            $query->offset($offset)->limit($limit);
        }

        $records = $query->get([
            'bf.id',
            'bf.login',
            'bf.ref_ticket',
            'bf.fee_category',
            'bf.fee_category_sub',
            'bf.amount',
            'bf.balance',
            'bf.created_at',
            DB::raw('ta.trade_account_type as account_type'),
        ])->map(static fn ($item) => is_array($item) ? $item : $item->toArray())->all();

        return [$records, $count];
    }

    private function baseQuery(): Builder
    {
        return $this->getQuery()->from('balance_flow as bf');
    }
}
