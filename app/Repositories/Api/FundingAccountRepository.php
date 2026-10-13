<?php

namespace App\Repositories\Api;

use App\Http\Requests\DataOverview\DataOverviewRequest;
use App\Models\Api\FundingAccount;
use App\Models\Api\User;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Repositories\Repository;

class FundingAccountRepository extends Repository
{
    public function __construct(FundingAccount $model)
    {
        parent::__construct($model);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUserCode(int $userCode): ?array
    {
        $row = $this->getQuery()
            ->where('user_code', $userCode)
            ->first(['user_code', 'total_balance', 'available_balance']);

        if (empty($row)) {
            return null;
        }

        return is_array($row) ? $row : $row->toArray();
    }

    // 获取资金账号可用余额
    public function getAvailableBalanceByUserCode(int $userCode): array
    {
        return $this->getQuery()
            ->where('user_code', $userCode)
            ->pluck('available_balance', 'user_code')
            ->toArray();
    }

    /**
     * 查询指定时间范围内首次入金用户数
     *
     * 统计条件：
     * - user.type 为真实用户（TYPE_REAL = 0）
     * - funding_account.first_deposited_at 在查询时间范围内
     *
     * @param string $startDateTime 开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime   结束时间，格式 Y-m-d H:i:s
     * @param string $customerType  客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     *
     * @return int 首次入金用户数
     */
    public function getFirstDepositUserCount(string $startDateTime, string $endDateTime, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL): int
    {
        $query = DB::connection(DB_API)
            ->table('funding_account as fa')
            ->join('user as u', 'u.user_code', '=', 'fa.user_code')
            ->whereIn('u.type', [User::TYPE_REAL, User::TYPE_BLACK])
            ->where('fa.first_deposited_at', '>=', $startDateTime)
            ->where('fa.first_deposited_at', '<=', $endDateTime);

        if ($customerType == DataOverviewRequest::CUSTOMER_TYPE_AGENT) {
            $query->whereExists(function ($subQuery) {
                $subQuery->selectRaw(1)
                    ->from('ib_account as ia')
                    ->whereColumn('ia.user_code', 'u.user_code');
            });
        }

        return (int) $query->count();
    }

    /**
     * 查询指定时间范围内按日期粒度分组的首次入金用户数
     *
     * 统计口径与 getFirstDepositUserCount 一致，按 fa.first_deposited_at 的日/周（周一起始）/月分组。
     *
     * @param string $startDateTime 开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime   结束时间，格式 Y-m-d H:i:s
     * @param string $customerType  客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     * @param string $dateType      日期粒度：day / week / month
     *
     * @return array<string, int> key: 期间起始日期（Y-m-d），value: 首次入金用户数
     */
    public function getFirstDepositUserCountByPeriod(string $startDateTime, string $endDateTime, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL, string $dateType = DataOverviewRequest::CHART_DATE_TYPE_DAY): array
    {
        $query = DB::connection(DB_API)
            ->table('funding_account as fa')
            ->join('user as u', 'u.user_code', '=', 'fa.user_code')
            ->whereIn('u.type', [User::TYPE_REAL, User::TYPE_BLACK])
            ->where('fa.first_deposited_at', '>=', $startDateTime)
            ->where('fa.first_deposited_at', '<=', $endDateTime);

        if ($customerType == DataOverviewRequest::CUSTOMER_TYPE_AGENT) {
            $query->whereExists(function ($subQuery) {
                $subQuery->selectRaw(1)
                    ->from('ib_account as ia')
                    ->whereColumn('ia.user_code', 'u.user_code');
            });
        }

        return $query
            ->selectRaw(self::periodExpression('fa.first_deposited_at', $dateType) . ' as stat_date, COUNT(*) as total_count')
            ->groupBy('stat_date')
            ->pluck('total_count', 'stat_date')
            ->map(static fn ($count): int => (int) $count)
            ->toArray();
    }

    /**
     * 生成按日/周/月分组的日期表达式，返回期间起始日期
     *
     * @param string $column 日期列（可带表别名）
     * @param string $dateType 日期粒度：day / week / month
     */
    private static function periodExpression(string $column, string $dateType): string
    {
        return match ($dateType) {
            DataOverviewRequest::CHART_DATE_TYPE_WEEK  => "DATE_SUB(DATE({$column}), INTERVAL WEEKDAY({$column}) DAY)",
            DataOverviewRequest::CHART_DATE_TYPE_MONTH => "DATE_FORMAT({$column}, '%Y-%m-01')",
            default                                    => "DATE({$column})",
        };
    }
}
