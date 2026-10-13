<?php

namespace App\Repositories\Et6;

use App\Http\Requests\DataOverview\DataOverviewRequest;
use App\Models\Api\User;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Enums\TypeTradeAccount;
use Scaffold\Framework\Repositories\Repository;
use App\Models\Et6\TradeTransResult;
use Scaffold\Framework\Models\Api\TsSource;

class TradeRepository extends Repository
{
    public function __construct(TradeTransResult $model)
    {
        parent::__construct($model);
    }

    /**
     * 按用户汇总全部交易账号的订单和盈利。
     *
     * @return array{profit: float, order_count: int}
     */
    public function getUserTradeSummary(int $userCode): array
    {
        $summary = DB::connection(DB_TS)
            ->table('trade_trans_result as t')
            ->join(DB::raw(DB_API . '.trade_account as ta'), 'ta.trade_account_id', '=', 't.login')
            ->where('ta.user_code', $userCode)
            ->whereIn('t.cmd', array_merge(
                range(0, TradeTransResult::CMD_MARKET_THRESHOLD - 1),
                TradeTransResult::PENDING_CMDS
            ))
            ->selectRaw(
                'COUNT(*) as order_count, '
                . 'SUM(CASE WHEN t.cmd < ? THEN '
                . 'COALESCE(t.profit, 0) + COALESCE(t.swaps, 0) + COALESCE(t.commission, 0) '
                . '+ COALESCE(t.open_fee, 0) + COALESCE(t.close_fee, 0) ELSE 0 END) as profit',
                [TradeTransResult::CMD_MARKET_THRESHOLD]
            )
            ->first();

        return [
            'profit'      => round((float) ($summary->profit ?? 0), 2),
            'order_count' => (int) ($summary->order_count ?? 0),
        ];
    }

    /**
     * @param array<int> $tradeAccountIds
     * @return array<int, array{profit:float, order_count:int}>
     */
    public function getTradeAccountSummaries(array $tradeAccountIds): array
    {
        if (empty($tradeAccountIds)) {
            return [];
        }

        return $this->getQuery()
            ->whereIn('login', $tradeAccountIds)
            ->whereIn('cmd', array_merge(
                range(0, TradeTransResult::CMD_MARKET_THRESHOLD - 1),
                TradeTransResult::PENDING_CMDS
            ))
            ->groupBy('login')
            ->selectRaw(
                'login, COUNT(*) as order_count, '
                . 'SUM(CASE WHEN cmd < ? THEN '
                . 'COALESCE(profit, 0) + COALESCE(swaps, 0) + COALESCE(commission, 0) '
                . '+ COALESCE(open_fee, 0) + COALESCE(close_fee, 0) ELSE 0 END) as profit',
                [TradeTransResult::CMD_MARKET_THRESHOLD]
            )
            ->get()
            ->mapWithKeys(static function ($summary): array {
                return [
                    (int) $summary->login => [
                        'profit'      => round((float) $summary->profit, 2),
                        'order_count' => (int) $summary->order_count,
                    ],
                ];
            })
            ->all();
    }

    /**
     * 获取订单列表
     *
     * @param int    $userCode 用户ID
     * @param int    $accountType 账号类型：-1全部，0统一交易账号，1跟单账号，2比赛账号，3赠金账户
     * @param int    $login
     * @param int    $ticket 订单号
     * @param int    $isClosed 是否已平仓：0全部，1未平仓，2已平仓
     * @param string $openStartTime 开仓开始时间
     * @param string $openEndTime 开仓结束时间
     * @param string $closeStartTime 平仓开始时间
     * @param string $closeEndTime 平仓结束时间
     * @param int    $profitMode 盈利模式：-1全部，0RWA，1Crypto
     * @param string $sort
     * @param string $order
     * @param int    $limit
     * @param int    $offset
     *
     * @return array
     * @throws Exception
     */
    public function getList($userCode, $accountType, $login, $ticket, $isClosed, $openStartTime, $openEndTime, $closeStartTime, $closeEndTime, $profitMode, $sort, $order, $limit, $offset)
    {
        $query = $this->getQuery()->from('trade_trans_result as t')->leftJoin('api.trade_account as ta', 'ta.trade_account_id', '=', 't.login')
            ->where('t.cmd', '<', TradeTransResult::CMD_MARKET_THRESHOLD);
        switch ($isClosed){
            case TradeTransResult::IS_CLOSED_FILTER_OPEN:
                $query->whereNull('t.close_time');
                break;
            case TradeTransResult::IS_CLOSED_FILTER_CLOSED:
                $query->whereNotNull('t.close_time');
                break;
        }

        if ($userCode) {
            $query->where('ta.user_code', $userCode);
        }

        if ($accountType != TradeTransResult::FILTER_ALL) {
            $query->where('ta.trade_account_type', $accountType);
        }

        if ($login) {
            $query->where('t.login', $login);
        }

        if ($ticket) {
            $query->where('t.ticket', $ticket);
        }

        if ($openStartTime && $openEndTime) {
            $query->whereBetween('t.open_time', [$openStartTime, $openEndTime]);
        }

        if ($closeStartTime && $closeEndTime) {
            $query->whereBetween('t.close_time', [$closeStartTime, $closeEndTime]);
        }

        if ($profitMode != TradeTransResult::FILTER_ALL) {
            $query->where('t.profit_mode', $profitMode);
        }
        $count = $query->count();

        $data = $query->orderBy($sort, $order)->offset($offset)->limit($limit)
            ->select('ta.user_code', 'ta.trade_account_type' ,'t.close_type', 't.login', 't.ticket', 't.cmd', 't.source', 't.leverage', 't.so_type', 't.open_time', 't.close_time', 't.open_price', 't.close_price', 't.margin_order', 't.position_size', 't.profit_mode', 't.profit', 't.swaps', 't.commission', 't.open_fee', 't.close_fee', 't.comment', 't.order_qty')
            ->get()->toArray();
        return [$data, $count];
    }

    /**
     * 获取挂单列表
     *
     * @param int    $userCode 用户ID
     * @param int    $login
     * @param int    $ticket 订单号
     * @param string $pendingStartTime 挂单开始时间
     * @param string $pendingEndTime 挂单结束时间
     * @param int    $profitMode 盈利模式：-1全部，0RWA，1Crypto
     * @param string $sort
     * @param string $order
     * @param int    $limit
     * @param int    $offset
     *
     * @return array
     * @throws Exception
     */
    public function getPendingList($userCode, $login, $ticket, $pendingStartTime, $pendingEndTime, $profitMode, $sort, $order, $limit, $offset){
        $query = $this->getQuery()->from('trade_trans_result as t')->leftJoin('api.trade_account as ta', 'ta.trade_account_id', '=', 't.login')
            ->where(function ($query) {
                $query->whereIn('t.cmd', TradeTransResult::PENDING_CMDS)
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('t.cmd', '<', TradeTransResult::CMD_MARKET_THRESHOLD)
                            ->where('t.comment', 'like', '%activate%');
                    });
            });

        if ($userCode) {
            $query->where('ta.user_code', $userCode);
        }

        if ($login) {
            $query->where('t.login', $login);
        }

        if ($ticket) {
            $query->where('t.ticket', $ticket);
        }

        if ($pendingStartTime && $pendingEndTime) {
            $query->whereBetween('t.open_time', [$pendingStartTime, $pendingEndTime]);
        }

        if ($profitMode != TradeTransResult::FILTER_ALL) {
            $query->where('t.profit_mode', $profitMode);
        }
        $count = $query->count();

        $data = $query->orderBy($sort, $order)->offset($offset)->limit($limit)
            ->select('ta.user_code', 't.login', 't.ticket', 't.cmd', 't.source', 't.leverage', 't.so_type', 't.open_time', 't.expiration', 't.open_price', 't.position_size', 't.swaps', 't.profit_mode', 'sl', 'tp', 't.comment', 't.order_qty')
            ->get()->toArray();

        return [$data, $count];
    }

    public function getOrderMetricsByTickets(array $tickets): array
    {
        $tickets = array_values(array_filter(array_map(
            static fn ($ticket) => trim((string) $ticket),
            $tickets
        ), static fn ($ticket) => $ticket !== ''));
        if (empty($tickets)) {
            return [];
        }

        return $this->getQuery()
            ->whereIn('ticket', $tickets)
            ->select('ticket', 'source', 'order_qty', 'position_size', 'open_time', 'close_time')
            ->get()
            ->mapWithKeys(static function ($item): array {
                $row = is_array($item) ? $item : $item->toArray();

                return [(string) ($row['ticket'] ?? '') => $row];
            })
            ->all();
    }

    /**
     * 查询指定时间范围内有交易的用户数
     *
     * 跨库查询 trading_system_user.trade_trans_result 与 api.trade_account、api.user，
     * 仅统计市价单（cmd < 2），按 user.type = TYPE_REAL 过滤真实用户。
     * 根据 trade_account_type 参数区分：
     * - real：排除试玩账户
     * - trial：仅统计试玩账户
     *
     * @param string $startDateTime     开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime       结束时间，格式 Y-m-d H:i:s
     * @param string $tradeAccountType 交易账户类型：real / trial
     * @param string $customerType      客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     * @param string $source            品种代码，精确过滤，空串表示不过滤
     *
     * @return int 有交易的用户数
     */
    public function getTradingUserCount(string $startDateTime, string $endDateTime, string $tradeAccountType, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL, string $source = ''): int
    {
        $query = self::tradingOverviewQuery($startDateTime, $endDateTime, $tradeAccountType, $customerType, $source);

        return $query->distinct()->count('ta.user_code');
    }

    /**
     * 查询指定时间范围内按日期粒度分组有交易的用户数
     *
     * 统计口径与 getTradingUserCount 一致，按 t.open_time 的日/周（周一起始）/月分组，
     * 每个期间独立去重（同一用户跨期间交易会在多个期间各计一次）。
     *
     * @param string $startDateTime     开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime       结束时间，格式 Y-m-d H:i:s
     * @param string $tradeAccountType 交易账户类型：real / trial
     * @param string $customerType      客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     * @param string $source            品种代码，精确过滤，空串表示不过滤
     * @param string $dateType          日期粒度：day / week / month
     *
     * @return array<string, int> key: 期间起始日期（Y-m-d），value: 有交易的用户数
     */
    public function getTradingUserCountByPeriod(string $startDateTime, string $endDateTime, string $tradeAccountType, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL, string $source = '', string $dateType = DataOverviewRequest::CHART_DATE_TYPE_DAY): array
    {
        $query = self::tradingOverviewQuery($startDateTime, $endDateTime, $tradeAccountType, $customerType, $source);

        return $query
            ->selectRaw(self::periodExpression('t.open_time', $dateType) . ' as stat_date, COUNT(DISTINCT ta.user_code) as total_count')
            ->groupBy('stat_date')
            ->pluck('total_count', 'stat_date')
            ->map(static fn ($count): int => (int) $count)
            ->toArray();
    }

    /**
     * 查询指定时间范围内的交易统计数据（单次查询返回全部维度）
     *
     * 跨库查询，返回聚合值：
     * - lots：SUM(t.lots)，交易总手数，保留 2 位小数
     * - amount：SUM(t.margin_origin)，交易总金额，保留 2 位小数
     * - order_count：COUNT(*)，订单总数
     *
     * 根据 trade_account_type 参数区分真实/试玩账户。
     *
     * @param string $startDateTime     开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime       结束时间，格式 Y-m-d H:i:s
     * @param string $tradeAccountType 交易账户类型：real / trial
     * @param string $customerType      客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     * @param string $source            品种代码，精确过滤，空串表示不过滤
     *
     * @return array{lots:float, amount:float, order_count:int}
     */
    public function getTradingStats(string $startDateTime, string $endDateTime, string $tradeAccountType, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL, string $source = ''): array
    {
        $query = self::tradingOverviewQuery($startDateTime, $endDateTime, $tradeAccountType, $customerType, $source);

        $row = $query->selectRaw(
            'COALESCE(SUM(t.lots), 0) as total_lots, '
            . 'COALESCE(SUM(t.margin_origin), 0) as total_amount, '
            . 'COUNT(*) as total_order_count'
        )->first();

        return [
            'lots'         => round((float) ($row->total_lots ?? 0), 2),
            'amount'       => round((float) ($row->total_amount ?? 0), 2),
            'order_count'  => (int) ($row->total_order_count ?? 0),
        ];
    }

    /**
     * 查询指定时间范围内按日期粒度分组的交易统计数据
     *
     * 统计口径与 getTradingStats 一致，按 t.open_time 的日/周（周一起始）/月分组。
     *
     * @param string $startDateTime     开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime       结束时间，格式 Y-m-d H:i:s
     * @param string $tradeAccountType 交易账户类型：real / trial
     * @param string $customerType      客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     * @param string $source            品种代码，精确过滤，空串表示不过滤
     * @param string $dateType          日期粒度：day / week / month
     *
     * @return array<string, array{lots:float, amount:float, order_count:int}> key: 期间起始日期（Y-m-d）
     */
    public function getTradingStatsByPeriod(string $startDateTime, string $endDateTime, string $tradeAccountType, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL, string $source = '', string $dateType = DataOverviewRequest::CHART_DATE_TYPE_DAY): array
    {
        $query = self::tradingOverviewQuery($startDateTime, $endDateTime, $tradeAccountType, $customerType, $source);

        $rows = $query
            ->selectRaw(
                self::periodExpression('t.open_time', $dateType) . ' as stat_date, '
                . 'COALESCE(SUM(t.lots), 0) as total_lots, '
                . 'COALESCE(SUM(t.margin_origin), 0) as total_amount, '
                . 'COUNT(*) as total_order_count'
            )
            ->groupBy('stat_date')
            ->get();

        return $rows->mapWithKeys(static function ($row): array {
            return [
                (string) $row->stat_date => [
                    'lots'         => round((float) $row->total_lots, 2),
                    'amount'       => round((float) $row->total_amount, 2),
                    'order_count'  => (int) $row->total_order_count,
                ],
            ];
        })->toArray();
    }

    /**
     * 查询指定时间范围内的品种交易排行列表（单次查询返回全部维度）
     *
     * 按交易品种（source）分组聚合，返回三个维度的排行列表，
     * 每个列表按各自统计值降序排列，并排除该维度值为 0 的品种。
     *
     * 统计维度：
     * - lot：SUM(t.lots)，交易手数，保留 2 位小数
     * - amount：SUM(t.margin_origin)，交易金额，保留 2 位小数
     * - order_count：COUNT(*)，订单数
     *
     * @param string $startDateTime     开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime       结束时间，格式 Y-m-d H:i:s
     * @param string $tradeAccountType 交易账户类型：real / trial
     * @param string $customerType      客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     * @param string $source            品种代码，精确过滤，空串表示不过滤
     *
     * @return array{lot:array<int, array{source:string, value:float}>, amount:array<int, array{source:string, value:float}>, order_count:array<int, array{source:string, value:int}>}
     */
    public function getSourceTradingRanks(string $startDateTime, string $endDateTime, string $tradeAccountType, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL, string $source = ''): array
    {
        $rows = self::tradingOverviewQuery($startDateTime, $endDateTime, $tradeAccountType, $customerType, $source)
            ->selectRaw(
                't.source, '
                . 'COALESCE(SUM(t.lots), 0) as total_lots, '
                . 'COALESCE(SUM(t.margin_origin), 0) as total_amount, '
                . 'COUNT(*) as total_order_count'
            )
            ->groupBy('t.source')
            ->get();

        $ranks = ['lot' => [], 'amount' => [], 'order_count' => []];

        foreach ($rows as $row) {
            $ranks['lot'][]         = ['source' => $row->source, 'value' => round((float) $row->total_lots, 2)];
            $ranks['amount'][]      = ['source' => $row->source, 'value' => round((float) $row->total_amount, 2)];
            $ranks['order_count'][] = ['source' => $row->source, 'value' => (int) $row->total_order_count];
        }

        foreach (array_keys($ranks) as $dimension) {
            usort($ranks[$dimension], static function (array $a, array $b): int {
                return $b['value'] <=> $a['value'];
            });

            $ranks[$dimension] = array_values(array_filter(
                $ranks[$dimension],
                static fn (array $item): bool => $item['value'] != 0
            ));
        }

        return $ranks;
    }

    /**
     * 查询指定维度的品种交易排行列表。
     *
     * @return array<int, array{source:string, value:float|int}>
     */
    public function getSourceTradingRank(string $startDateTime, string $endDateTime, string $type, string $tradeAccountType, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL): array
    {
        $aggregate = match ($type) {
            DataOverviewRequest::TRADING_TYPE_AMOUNT => 'COALESCE(SUM(t.margin_origin), 0)',
            DataOverviewRequest::TRADING_TYPE_ORDER_COUNT => 'COUNT(*)',
            default => 'COALESCE(SUM(t.lots), 0)',
        };

        $rows = self::tradingOverviewQuery($startDateTime, $endDateTime, $tradeAccountType, $customerType, '')
            ->selectRaw("t.source, {$aggregate} as total_value")
            ->groupBy('t.source')
            ->havingRaw("{$aggregate} != 0")
            ->orderByDesc('total_value')
            ->get();

        return $rows->map(static function ($row) use ($type): array {
            return [
                'source' => (string) $row->source,
                'value' => $type === DataOverviewRequest::TRADING_TYPE_ORDER_COUNT
                    ? (int) $row->total_value
                    : round((float) $row->total_value, 2),
            ];
        })->all();
    }

    /**
     * 查询指定品种分类的订单数。
     *
     * @return array<string, int> key: source_type，value: 订单数
     */
    public function getOrderCountsBySourceType(string $startDateTime, string $endDateTime, string $tradeAccountType, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL): array
    {
        return self::tradingOverviewQuery($startDateTime, $endDateTime, $tradeAccountType, $customerType, '')
            ->join(DB::raw(DB_API . '.ts_source as s'), 's.source', '=', 't.source')
            ->whereIn('s.category', [
                TsSource::METAL,
                TsSource::INDEX,
                TsSource::CRYPTO,
                TsSource::FOREX,
                TsSource::ENERGY,
            ])
            ->selectRaw('s.category as source_type, COUNT(*) as total_order_count')
            ->groupBy('s.category')
            ->pluck('total_order_count', 'source_type')
            ->map(static fn ($count): int => (int) $count)
            ->toArray();
    }

    /**
     * 构建数据概览交易类统计的基础查询
     *
     * 跨库查询 trading_system_user.trade_trans_result 与 api.trade_account、api.user，
     * 仅统计市价单（cmd < 2）与真实/黑名单用户，统一应用账户类型、客户类型、品种过滤。
     *
     * @param string $startDateTime     开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime       结束时间，格式 Y-m-d H:i:s
     * @param string $tradeAccountType 交易账户类型：real / trial
     * @param string $customerType      客户类型：all（全部）/ ib（代理客户）
     * @param string $source            品种代码，精确过滤，空串表示不过滤
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private static function tradingOverviewQuery(string $startDateTime, string $endDateTime, string $tradeAccountType, string $customerType, string $source)
    {
        $query = DB::connection(DB_TS)
            ->table('trade_trans_result as t')
            ->join(DB::raw(DB_API . '.trade_account as ta'), 'ta.trade_account_id', '=', 't.login')
            ->join(DB::raw(DB_API . '.user as u'), 'u.user_code', '=', 'ta.user_code')
            ->where('t.cmd', '<', 2) // 仅市价单
            ->where('t.open_time', '>=', $startDateTime)
            ->where('t.open_time', '<=', $endDateTime)
            ->whereIn('u.type', [User::TYPE_REAL, User::TYPE_BLACK]);

        if ($tradeAccountType == DataOverviewRequest::TRADE_ACCOUNT_TYPE_REAL) {
            $query->whereNotIn('ta.trade_account_type', self::trialTradeAccountTypeValues());
        } else {
            $query->whereIn('ta.trade_account_type', self::trialTradeAccountTypeValues());
        }

        $query = self::applyCustomerTypeFilter($query, $customerType);
        $query = self::applySourceFilter($query, $source);

        return $query;
    }

    /**
     * 客户类型过滤：ib 时仅保留代理客户（user_code 存在于 ib_account 表）
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string                             $customerType
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private static function applyCustomerTypeFilter($query, string $customerType)
    {
        if ($customerType == DataOverviewRequest::CUSTOMER_TYPE_AGENT) {
            $query->whereExists(function ($subQuery) {
                $subQuery->selectRaw(1)
                    ->from(DB::raw(DB_API . '.ib_account as ia'))
                    ->whereColumn('ia.user_code', 'u.user_code');
            });
        }

        return $query;
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
     * 品种代码精确过滤，空串表示不过滤
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @param string                             $source
     *
     * @return \Illuminate\Database\Query\Builder
     */
    private static function applySourceFilter($query, string $source)
    {
        if ($source !== '') {
            $query->where('t.source', $source);
        }

        return $query;
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
