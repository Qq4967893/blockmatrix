<?php

namespace App\Repositories\Api;

use App\Models\Api\MasterCommissionRecord;
use App\Models\Api\User;
use Illuminate\Support\Facades\DB;

class MasterCommissionRecordRepository
{
    /**
     * 统计指定结算时间范围内已分润的高手返佣金额。
     */
    public function getSettledCommissionAmount(string $startDateTime, string $endDateTime): float
    {
        return (float) $this->settledCommissionQuery($startDateTime, $endDateTime)
            ->join('user as u', 'u.user_code', '=', 'master_commission_record.master_user_code')
            ->whereIn('u.type', [User::TYPE_REAL, User::TYPE_BLACK])
            ->sum('commission_amount');
    }

    /**
     * 统计指定结算时间范围内返佣给高手的客户数（按跟单客户 user_code 去重）。
     */
    public function getSettledCommissionCustomerCount(string $startDateTime, string $endDateTime): int
    {
        return (int) $this->settledCommissionQuery($startDateTime, $endDateTime)
            ->join('user as u', 'u.user_code', '=', 'master_commission_record.follower_user_code')
            ->whereIn('u.type', [User::TYPE_REAL, User::TYPE_BLACK])
            ->distinct()
            ->count('follower_user_code');
    }

    /**
     * 构建已结算高手返佣记录的基础查询。
     *
     * @param string $startDateTime 开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime   结束时间，格式 Y-m-d H:i:s
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private function settledCommissionQuery(string $startDateTime, string $endDateTime)
    {
        return DB::connection(DB_API)
            ->table('master_commission_record')
            ->where('status', MasterCommissionRecord::STATUS_SETTLED)
            ->whereBetween('settled_at', [$startDateTime, $endDateTime]);
    }
}
