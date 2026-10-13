<?php

namespace App\Repositories\Api;

use App\Models\Api\FundingLedger;
use Illuminate\Database\Eloquent\Builder;
use Scaffold\Framework\Enums\TypeLedger;
use Scaffold\Framework\Repositories\Repository;

class FundingLedgerRepository extends Repository
{
    /**
     * @param FundingLedger $model 资金流水模型
     */
    public function __construct(FundingLedger $model)
    {
        parent::__construct($model);
    }

    /**
     * 分页获取资金流水列表
     *
     * @param array{
     *     user_code?: int|null,
     *     operation_source?: int|null,
     *     direction?: int|null,
     *     created_start?: string|null,
     *     created_end?: string|null,
     *     fund_type?: array<int, int>|null
     * } $filters 查询筛选条件
     * @param int $limit 每页条数，传 -1 时不限制
     * @param int $offset 偏移量，传 -1 时不分页
     *
     * @return array{0: array<int, array>, 1: int}
     */
    public function paginate(array $filters, int $limit, int $offset): array
    {
        $query = $this->baseQuery();

        if (!empty($filters['user_code'])) {
            $query->where('fl.user_code', (int) $filters['user_code']);
        }

        if (!empty($filters['operation_source'])) {
            $query->where('fl.operation_source', (int) $filters['operation_source']);
        }

        if (!empty($filters['direction'])) {
            $query->where('fl.direction', (int) $filters['direction']);
        }

        if (!empty($filters['created_start'])) {
            $query->where('fl.created_at', '>=', $filters['created_start']);
        }

        if (!empty($filters['created_end'])) {
            $query->where('fl.created_at', '<=', $filters['created_end']);
        }

        if (!empty($filters['fund_type'])) {
            $query->whereIn('fl.fund_type', $filters['fund_type']);
        }

        $count = (clone $query)->count('fl.id');

        $query->orderByDesc('fl.id');
        if ($offset !== -1 && $limit !== -1) {
            $query->offset($offset)->limit($limit);
        }

        $records = $query->get([
            'fl.id',
            'fl.user_code',
            'fl.direction',
            'fl.fund_type',
            'fl.operation_source',
            'fl.apply_amount',
            'fl.net_amount',
            'fl.after_balance',
            'fl.biz_id',
            'fl.order_id',
            'fl.trade_account_id',
            'fl.remark',
            'fl.created_at',
            'fa.reference_content as apply_reference_content',
        ])->map(static fn ($item) => is_array($item) ? $item : $item->toArray())->all();

        return [$records, $count];
    }

    /**
     * 获取用户赠金按收支方向汇总后的净额
     *
     * @param int|string $userCode 用户ID
     *
     * @return array<int|string, mixed>
     */
    public function getBonusByUserCode($userCode)
    {
        return $this->getQuery()->where('user_code', $userCode)
            ->where('fund_type', TypeLedger::Bonus->value)
            ->groupBy('direction')
            ->selectRaw('direction, sum(net_amount) as total_net_amount')
            ->pluck('total_net_amount', 'direction')
            ->toArray();
    }

    /**
     * 构建资金流水基础查询，并关联资金申请附加信息
     *
     * @return Builder
     */
    private function baseQuery(): Builder
    {
        return $this->getQuery()
            ->from('funding_ledger as fl')
            ->leftJoin('funding_apply as fa', 'fa.id', '=', 'fl.biz_id');
    }
}
