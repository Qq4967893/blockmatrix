<?php

namespace App\Repositories\Api;

use App\Models\Api\User;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Enums\TypeIbSettlementStatus;

class IbCommissionSettlementRepository
{
    /**
     * 统计指定结算时间范围内已结算的 IB 返佣金额。
     *
     * @param string $startDateTime 开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime   结束时间，格式 Y-m-d H:i:s
     * @param string $source        品种代码，空串表示不过滤
     */
    public function getCommissionAmount(string $startDateTime, string $endDateTime, string $source = ''): float
    {
        return (float) $this->settledCommissionSettlementQuery($startDateTime, $endDateTime, $source)
            ->sum('ics.commission_amount');
    }

    /**
     * 统计指定结算时间范围内获得已结算返佣的 IB 客户数（按 user_code 去重）。
     *
     * @param string $startDateTime 开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime   结束时间，格式 Y-m-d H:i:s
     * @param string $source        品种代码，空串表示不过滤
     */
    public function getCommissionCustomerCount(string $startDateTime, string $endDateTime, string $source = ''): int
    {
        return (int) $this->settledCommissionSettlementQuery($startDateTime, $endDateTime, $source)
            ->distinct()
            ->count('ics.user_code');
    }

    /**
     * 构建已结算 IB 返佣记录的基础查询，并排除白名单用户。
     *
     * @param string $startDateTime 开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime   结束时间，格式 Y-m-d H:i:s
     * @param string $source        品种代码，空串表示不过滤
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function settledCommissionSettlementQuery(string $startDateTime, string $endDateTime, string $source)
    {
        $query = DB::connection(DB_API)
            ->table('ib_commission_settlement as ics')
            ->join('user as u', 'u.user_code', '=', 'ics.user_code')
            ->where('ics.settlement_status', TypeIbSettlementStatus::Settled->value)
            ->whereIn('u.type', [User::TYPE_REAL, User::TYPE_BLACK])
            ->whereBetween('ics.settled_at', [$startDateTime, $endDateTime]);

        if ($source !== '') {
            $query->join('ib_commission_record as icr', 'icr.ticket', '=', 'ics.ticket')
                ->where('icr.source', $source);
        }

        return $query;
    }
}
