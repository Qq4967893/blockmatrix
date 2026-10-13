<?php

namespace App\Repositories\Api;

use App\Http\Requests\DataOverview\DataOverviewRequest;
use App\Models\Api\FundingApply;
use App\Models\Api\User;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Repositories\Repository;

class UserRepository extends Repository
{
    public function __construct(
        User $model
    ) {
        parent::__construct($model);
    }

    /**
     * 客户列表
     *
     * @param  string  $searchValue  关键字
     * @param  int  $payStatus  是否充值 0-全部 1-是 2-否
     * @param  int|null  $ibAffiliation  代理归属
     * @param  string  $registerTimeStart  注册开始时间
     * @param  string  $registerTimeEnd  注册结束时间
     * @param  string  $sort  排序字段
     * @param  string  $order  排序方式
     * @param  int  $limit  每页条数
     * @param  int  $offset  偏移量
     * @return array
     *
     * @throws \Exception
     */
    public function list(
        $searchValue,
        $payStatus,
        $ibAffiliation,
        $registerTimeStart,
        $registerTimeEnd,
        $sort = 'id',
        $order = 'desc',
        $limit = 20,
        $offset = 0
    ) {
        $query = $this->getQuery()->select('user.*');

        // 关键字查询
        if (! empty($searchValue)) {
            $query->where($this->buildAnyWayQuery($searchValue));
        }
        // 是否充值：以成功入金记录为准
        if ($payStatus == User::PAY_STATUS_PAID) {
            $query->whereIn('user.user_code', function ($subQuery) {
                $subQuery->select('fa.user_code')
                    ->from('funding_apply as fa')
                    ->where('fa.direction', FundingApply::DIRECTION_DEPOSIT)
                    ->where('fa.status', FundingApply::STATUS_APPROVED)
                    ->groupBy('fa.user_code');
            });
        } elseif ($payStatus == User::PAY_STATUS_UNPAID) {
            $query->whereNotIn('user.user_code', function ($subQuery) {
                $subQuery->select('fa.user_code')
                    ->from('funding_apply as fa')
                    ->where('fa.direction', FundingApply::DIRECTION_DEPOSIT)
                    ->where('fa.status', FundingApply::STATUS_APPROVED)
                    ->groupBy('fa.user_code');
            });
        }

        if ($ibAffiliation !== null && $ibAffiliation !== '') {
            $query->where('user.ib_affiliation', (int) $ibAffiliation);
        }

        // 注册时间
        if (! empty($registerTimeStart) && ! empty($registerTimeEnd)) {
            $query->where('created_at', '>=', $registerTimeStart)
                ->where('created_at', '<=', $registerTimeEnd);
        }

        $count = $query->count();
        $data = $query->orderBy("{$sort}", $order)->limit($limit)->offset($offset)->get()->toArray();

        return [$data, $count];
    }

    /**
     * 已入金客户
     *
     * @param  array  $userCodes
     * @return array
     *
     * @throws \Exception
     */
    public function hasBeenCashInflow($userCodes)
    {
        $query = DB::connection(DB_API)->table('funding_apply as fa');
        if (! empty($userCodes)) {
            $query->whereIn('fa.user_code', $userCodes);
        }

        return $query->where('fa.direction', FundingApply::DIRECTION_DEPOSIT)
            ->where('fa.status', FundingApply::STATUS_APPROVED)
            ->groupBy('fa.user_code')
            ->pluck('fa.user_code')
            ->toArray();
    }

    /**
     * 按交易账号查询直客（无代理归属）用户的用户ID映射。
     *
     * 仅返回同时满足以下条件的交易账号：
     * - 交易账号存在对应的用户
     * - 用户不是代理（不存在 ib_account 记录）
     * - 用户没有上级代理（user.ib_affiliation 为空或 0）
     *
     * @param  array<int, int|string>  $tradeAccountIds  交易账号ID列表
     * @return array<int, int> trade_account_id => user_code
     */
    public function getDirectCustomerUserCodesByTradeAccountIds(array $tradeAccountIds): array
    {
        if (empty($tradeAccountIds)) {
            return [];
        }

        return DB::connection(DB_API)
            ->table('trade_account as ta')
            ->join('user as u', 'u.user_code', '=', 'ta.user_code')
            ->leftJoin('ib_account as ib', 'ib.user_code', '=', 'u.user_code')
            ->whereIn('ta.trade_account_id', $tradeAccountIds)
            ->where(static function ($query) {
                $query->whereNull('u.ib_affiliation')->orWhere('u.ib_affiliation', 0);
            })
            ->whereNull('ib.user_code')
            ->pluck('u.user_code', 'ta.trade_account_id')
            ->map(static fn ($userCode): int => (int) $userCode)
            ->toArray();
    }

    /**
     * 客户列表 搜索查询
     *
     * @param  string  $searchValue  搜索内容
     * @return \Closure
     *
     * @throws \Exception
     */
    public function buildAnyWayQuery($searchValue)
    {
        $keyword = $this->like($searchValue);

        return function ($query) use ($keyword) {
            $query->where('user.user_code', 'like', $keyword)
                ->orWhere('user.email', 'like', $keyword)
                ->orWhere('user.username', 'like', $keyword);
        };
    }

    /**
     * 根据交易账号 ID 获取对应的用户名映射
     *
     * @param  array  $tradeAccountId  交易账号 ID 列表
     */
    public function getUsernamesByTradeAccountId(array $tradeAccountId): array
    {
        return $this->getQuery()->leftJoin('trade_account', 'user.user_code', '=', 'trade_account.user_code')
            ->whereIn('trade_account.trade_account_id', $tradeAccountId)
            ->pluck('username', 'trade_account_id')
            ->toArray();
    }

    private function like(string $value): string
    {
        $escaped = addcslashes($value, '%_');

        return sprintf('%%%s%%', $escaped);
    }

    /**
     * 根据用户编码下线客户、触发模型事件app自动下线
     *
     * @param  int  $userCode  用户编码
     * @return bool
     */
    public function downLine($userCode)
    {
        return $this->update(['access_token' => null], $userCode, 'user_code');
    }

    public function updateByUserCode(int $userCode, array $payload): bool
    {
        return $this->update($payload, $userCode, 'user_code');
    }

    public function findUserCodeByEmail(string $email): ?int
    {
        $userCode = $this->getQuery()
            ->where('email', strtolower(trim($email)))
            ->value('user_code');

        return $userCode === null ? null : (int) $userCode;
    }

    public function isWalletUser(int $userCode): bool
    {
        $user = $this->firstBy('user_code', $userCode, ['user_code', 'wallet_address']);

        return (bool) $user?->isWalletUser();
    }

    /**
     * @param  array<int, int>  $userCodes
     * @param  array<int, string>  $columns
     * @return array<int, array>
     */
    public function getByUserCodes(array $userCodes, array $columns = ['user_code', 'username', 'type']): array
    {
        if ($userCodes === []) {
            return [];
        }

        return $this->getQuery()
            ->whereIn('user_code', $userCodes)
            ->get($columns)
            ->toArray();
    }

    /**
     * 查询指定时间范围内的注册人数
     *
     * 仅统计 user.type 为真实用户（TYPE_REAL = 0）的注册记录，
     * 自动排除白名单用户（TYPE_TEST）和黑名单用户（TYPE_BLACK）。
     *
     * @param  string  $startDateTime  开始时间，格式 Y-m-d H:i:s
     * @param  string  $endDateTime  结束时间，格式 Y-m-d H:i:s
     * @param  string  $customerType  客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     * @return int 注册用户数量
     */
    public function getRegisteredUserCount(string $startDateTime, string $endDateTime, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL): int
    {
        $query = DB::connection(DB_API)
            ->table('user')
            ->whereIn('type', [User::TYPE_REAL, User::TYPE_BLACK])
            ->where('created_at', '>=', $startDateTime)
            ->where('created_at', '<=', $endDateTime);

        if ($customerType == DataOverviewRequest::CUSTOMER_TYPE_AGENT) {
            $query->whereExists(function ($subQuery) {
                $subQuery->selectRaw(1)
                    ->from('ib_account as ia')
                    ->whereColumn('ia.user_code', 'user.user_code');
            });
        }

        return $query->count();
    }

    /**
     * 查询指定时间范围内按日期粒度分组的注册人数
     *
     * 统计口径与 getRegisteredUserCount 一致，按 created_at 的日/周（周一起始）/月分组。
     *
     * @param  string  $startDateTime  开始时间，格式 Y-m-d H:i:s
     * @param  string  $endDateTime  结束时间，格式 Y-m-d H:i:s
     * @param  string  $customerType  客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     * @param  string  $dateType  日期粒度：day / week / month
     * @return array<string, int> key: 期间起始日期（Y-m-d），value: 注册人数
     */
    public function getRegisteredUserCountByPeriod(string $startDateTime, string $endDateTime, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL, string $dateType = DataOverviewRequest::CHART_DATE_TYPE_DAY): array
    {
        $query = DB::connection(DB_API)
            ->table('user')
            ->whereIn('type', [User::TYPE_REAL, User::TYPE_BLACK])
            ->where('created_at', '>=', $startDateTime)
            ->where('created_at', '<=', $endDateTime);

        if ($customerType == DataOverviewRequest::CUSTOMER_TYPE_AGENT) {
            $query->whereExists(function ($subQuery) {
                $subQuery->selectRaw(1)
                    ->from('ib_account as ia')
                    ->whereColumn('ia.user_code', 'user.user_code');
            });
        }

        return $query
            ->selectRaw(self::periodExpression('created_at', $dateType).' as stat_date, COUNT(*) as total_count')
            ->groupBy('stat_date')
            ->pluck('total_count', 'stat_date')
            ->map(static fn ($count): int => (int) $count)
            ->toArray();
    }

    /**
     * 生成按日/周/月分组的日期表达式，返回期间起始日期
     *
     * @param  string  $column  日期列（可带表别名）
     * @param  string  $dateType  日期粒度：day / week / month
     */
    private static function periodExpression(string $column, string $dateType): string
    {
        return match ($dateType) {
            DataOverviewRequest::CHART_DATE_TYPE_WEEK => "DATE_SUB(DATE({$column}), INTERVAL WEEKDAY({$column}) DAY)",
            DataOverviewRequest::CHART_DATE_TYPE_MONTH => "DATE_FORMAT({$column}, '%Y-%m-01')",
            default => "DATE({$column})",
        };
    }
}
