<?php

namespace App\Repositories\Api;

use App\Http\Requests\DataOverview\DataOverviewRequest;
use App\Models\Api\User;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Models\Api\TradeAccount;
use Scaffold\Framework\Repositories\Repository;
use Scaffold\Framework\Enums\TypeTradeAccount;

class TradeAccountRepository extends Repository
{
    public const STATUS_NORMAL = 1;

    public function __construct(
        TradeAccount $model
    ) {
        parent::__construct($model);
    }

    /**
     * 获取指定客户的全部交易账号
     */
    public function getAccountsByUserCode(int $userCode): array
    {
        return $this->getQuery()
            ->where('user_code', $userCode)
            ->where('status', self::STATUS_NORMAL)
            ->whereNotNull('trade_account_id')
            ->orderByDesc('trade_account_id')
            ->get()
            ->map(static fn($item) => is_array($item) ? $item : $item->toArray())
            ->filter(function ($account) {
                $tradeAccountId = (int) ($account['trade_account_id'] ?? 0);
                return $tradeAccountId > 0;
            })
            ->values()
            ->all();
    }

    /**
     * 获取指定客户的跟单交易账号 ID 列表
     *
     * @param int $userCode 客户 ID
     *
     * @return array
     */
    public function getFollowerAccountsByUserCode(int $userCode): array
    {
        return $this->getQuery()
            ->where('user_code', $userCode)
            ->where('status', self::STATUS_NORMAL)
            ->where('trade_account_type', TypeTradeAccount::Copy->value)
            ->pluck('trade_account_id')
            ->toArray();
    }

    /**
     * 判断指定客户是否有赠金交易账号。
     */
    public function existsBonusFundByUserCode(int $userCode): bool
    {
        return $this->getQuery()
            ->where('user_code', $userCode)
            ->where('trade_account_type', TypeTradeAccount::BonusFund->value)
            ->exists();
    }

    /**
     * 获取指定客户的 UTA 交易账号
     */
    public function getUtaAccountsByUserCode(int $userCode): array
    {
        return collect($this->getAccountsByUserCode($userCode))
            ->filter(fn ($account) => (int) ($account['trade_account_type'] ?? -1) === TypeTradeAccount::UTA->value)
            ->values()
            ->all();
    }

    /**
     * 获取指定客户可展示为高手的交易账号（UTA / 赠金）。
     */
    public function getMasterDisplayAccountsByUserCode(int $userCode): array
    {
        $allowedTypes = [
            TypeTradeAccount::UTA->value,
            TypeTradeAccount::BonusFund->value,
        ];

        return collect($this->getAccountsByUserCode($userCode))
            ->filter(fn ($account) => in_array((int) ($account['trade_account_type'] ?? -1), $allowedTypes, true))
            ->values()
            ->all();
    }

    /**
     * 通过交易账号 ID 获取账号信息
     *
     * @param int $tradeAccountId 交易账号ID
     *
     * @return array|null
     */
    public function getAccountByTradeAccountId(int $tradeAccountId): ?array
    {
        $account = $this->getQuery()
            ->where('trade_account_id', $tradeAccountId)
            ->where('status', self::STATUS_NORMAL)
            ->whereNotNull('trade_account_id')
            ->first();

        if ($account === null) {
            return null;
        }

        return is_array($account) ? $account : $account->toArray();
    }

    /**
     * 获取 UTA 交易账号 ID 列表
     *
     * @param int $userCode 用户编码
     *
     * @return array<int>
     */
    public function getTradeAccountIdsByUserCode(int $userCode): array
    {
        return collect($this->getUtaAccountsByUserCode($userCode))
            ->pluck('trade_account_id')
            ->map(static fn($tradeAccountId) => (int) $tradeAccountId)
            ->all();
    }

    /**
     * 查询指定时间范围内首次入金的非试玩交易账户用户数
     *
     * 统计条件：
     * - user.type 为真实用户（TYPE_REAL = 0）
     * - trade_account.trade_account_type 排除试玩账户
     * - trade_account.first_deposited_at 在查询时间范围内
     *
     * @param string $startDateTime 开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime   结束时间，格式 Y-m-d H:i:s
     *
     * @return int 首次入金用户数
     */
    public function getFirstDepositUserCount(string $startDateTime, string $endDateTime): int
    {
        return (int) DB::connection(DB_API)
            ->table('trade_account as ta')
            ->join('user as u', 'u.user_code', '=', 'ta.user_code')
            ->where('u.type', User::TYPE_REAL)
            ->whereNotIn('ta.trade_account_type', self::trialTradeAccountTypeValues())
            ->where('ta.first_deposited_at', '>=', $startDateTime)
            ->where('ta.first_deposited_at', '<=', $endDateTime)
            ->distinct()
            ->count('ta.user_code');
    }

    /**
     * 查询指定时间范围内注册的试玩账户用户数
     *
     * 统计条件：
     * - user.type 为真实用户或黑名单用户
     * - trade_account.trade_account_type 为试玩账户
     * - trade_account.created_at 在查询时间范围内
     *
     * @param string $startDateTime 开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime   结束时间，格式 Y-m-d H:i:s
     * @param string $customerType  客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     *
     * @return int 试玩账户用户数
     */
    public function getRegisteredBonusFundCount(string $startDateTime, string $endDateTime, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL): int
    {
        $query = DB::connection(DB_API)
            ->table('trade_account as ta')
            ->join('user as u', 'u.user_code', '=', 'ta.user_code')
            ->whereIn('u.type', [User::TYPE_REAL, User::TYPE_BLACK])
            ->whereIn('ta.trade_account_type', self::trialTradeAccountTypeValues())
            ->where('ta.created_at', '>=', $startDateTime)
            ->where('ta.created_at', '<=', $endDateTime);

        if ($customerType == DataOverviewRequest::CUSTOMER_TYPE_AGENT) {
            $query->whereExists(function ($subQuery) {
                $subQuery->selectRaw(1)
                    ->from('ib_account as ia')
                    ->whereColumn('ia.user_code', 'u.user_code');
            });
        }

        return (int) $query
            ->distinct()
            ->count('ta.user_code');
    }

    /**
     * 查询指定时间范围内按日期粒度分组注册的试玩账户用户数
     *
     * 统计口径与 getRegisteredBonusFundCount 一致，按 ta.created_at 的日/周（周一起始）/月分组。
     *
     * @param string $startDateTime 开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime   结束时间，格式 Y-m-d H:i:s
     * @param string $customerType  客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     * @param string $dateType      日期粒度：day / week / month
     *
     * @return array<string, int> key: 期间起始日期（Y-m-d），value: 试玩账户用户数
     */
    public function getRegisteredBonusFundCountByPeriod(string $startDateTime, string $endDateTime, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL, string $dateType = DataOverviewRequest::CHART_DATE_TYPE_DAY): array
    {
        $query = DB::connection(DB_API)
            ->table('trade_account as ta')
            ->join('user as u', 'u.user_code', '=', 'ta.user_code')
            ->whereIn('u.type', [User::TYPE_REAL, User::TYPE_BLACK])
            ->whereIn('ta.trade_account_type', self::trialTradeAccountTypeValues())
            ->where('ta.created_at', '>=', $startDateTime)
            ->where('ta.created_at', '<=', $endDateTime);

        if ($customerType == DataOverviewRequest::CUSTOMER_TYPE_AGENT) {
            $query->whereExists(function ($subQuery) {
                $subQuery->selectRaw(1)
                    ->from('ib_account as ia')
                    ->whereColumn('ia.user_code', 'u.user_code');
            });
        }

        return $query
            ->selectRaw(self::periodExpression('ta.created_at', $dateType) . ' as stat_date, COUNT(DISTINCT ta.user_code) as total_count')
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

    /**
     * @return array<int, int>
     */
    private static function trialTradeAccountTypeValues(): array
    {
        return array_values(array_map(
            static fn (TypeTradeAccount $type): int => $type->value,
            array_filter(
                TypeTradeAccount::cases(),
                static fn (TypeTradeAccount $type): bool => $type->group() === 'Trial_Default'
            )
        ));
    }
}
