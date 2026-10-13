<?php

namespace App\Services\Api;

use App\Http\Requests\DataOverview\DataOverviewRequest;
use App\Models\Api\User;
use App\Repositories\Api\TradeAccountRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Api\FundingAccountRepository;
use App\Repositories\Api\FundingApplyRepository;
use App\Repositories\Api\IbCommissionSettlementRepository;
use App\Repositories\Api\MasterCommissionRecordRepository;
use App\Repositories\Et6\TradeRepository;
use Scaffold\Framework\Models\Api\TsSource;

class DataOverviewService
{
    public function __construct(
        private UserRepository $userRepo,
        private TradeAccountRepository $tradeAccountRepo,
        private FundingAccountRepository $fundingAccountRepo,
        private FundingApplyRepository $fundingApplyRepo,
        private IbCommissionSettlementRepository $ibCommissionSettlementRepo,
        private MasterCommissionRecordRepository $masterCommissionRecordRepo,
        private TradeRepository $tradeRepo,
    ) {
    }

    /**
     * 获取数据概览
     *
     * 根据 trade_account_type 区分统计口径：
     * - real：统计非赠金账户，首次入金用户数、入出金金额、IB 返佣金额、返佣客户数、高手返佣金额与返佣客户数正常计算
     * - trial：统计赠金账户，首次入金用户数、入出金金额、IB 返佣金额、返佣客户数、高手返佣金额与返佣客户数固定为 0
     *
     * 赠金账户与代理客户（trade_account_type=trial 且 customer_type=ib）组合无业务意义，直接返回空数据。
     *
     * source_trading_type：指定品种排行维度（lot / amount / order_count），返回单一 source_trading_rank 列表。
     * source_type_order_count_distribution：贵金属、指数、虚拟货币、外汇、能源五类品种的订单数及百分比分布。
     * chart：图表分组，按 chartDateType 粒度（日/周（周一起始）/月）统计各指标独立列表
     * （daily_* 各指标，元素为 {date, value}，date 为期间起始日期，无数据的期间补 0），
     * 仅作用于 chart 分组；其中 daily_trading_users 为每个期间独立去重，逐期累加可能大于总去重人数。
     *
     * @param string $dateStart         开始日期，格式 Y-m-d，查询时拼接为当天 00:00:00
     * @param string $dateEnd           结束日期，格式 Y-m-d，查询时拼接为当天 23:59:59
     * @param string $tradeAccountType  交易账户类型：real（非赠金）/ trial（赠金）
     * @param string $customerType      客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     * @param string $sourceTradingType 品种排行维度：lot（手数）/ amount（金额）/ order_count（订单数）
     * @param string $chartDateType     图表日期粒度：day（日）/ week（周）/ month（月），仅作用于 chart 分组
     *
     * @return array{registered_users:int, first_deposit_users:int, trading_users:int, trading_lots:float, trading_amount:float, order_count:int, deposit_amount:float, withdrawal_amount:float, net_deposit_amount:float, ib_commission_amount:float, ib_commission_customer_count:int, master_commission_amount:float, master_commission_customer_count:int, source_trading_rank:array<int, array{source:string, value:float|int}>, source_type_order_count_distribution:array<int, array{source_type:string, order_count:int, percentage:float}>, chart:array{daily_registered_users:array<int, array{date:string, value:int}>, daily_first_deposit_users:array<int, array{date:string, value:int}>, daily_trading_users:array<int, array{date:string, value:int}>, daily_trading_lots:array<int, array{date:string, value:float}>, daily_trading_amount:array<int, array{date:string, value:float}>, daily_order_count:array<int, array{date:string, value:int}>, daily_deposit_amount:array<int, array{date:string, value:float}>, daily_withdrawal_amount:array<int, array{date:string, value:float}>, daily_net_deposit_amount:array<int, array{date:string, value:float}>}}
     */
    public function getOverview(string $dateStart, string $dateEnd, string $tradeAccountType, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL, string $sourceTradingType = DataOverviewRequest::TRADING_TYPE_LOT, string $chartDateType = DataOverviewRequest::CHART_DATE_TYPE_WEEK): array
    {
        if ($tradeAccountType == DataOverviewRequest::TRADE_ACCOUNT_TYPE_TRIAL
            && $customerType == DataOverviewRequest::CUSTOMER_TYPE_AGENT) {
            return $this->emptyOverview();
        }

        $startDateTime = $dateStart . ' 00:00:00';
        $endDateTime   = $dateEnd . ' 23:59:59';

        if ($tradeAccountType == DataOverviewRequest::TRADE_ACCOUNT_TYPE_REAL) {
            $registered_Users = $this->userRepo->getRegisteredUserCount($startDateTime, $endDateTime, $customerType);
            $firstDepositUsers = $this->fundingAccountRepo->getFirstDepositUserCount($startDateTime, $endDateTime, $customerType);
            $amountTotals = $this->fundingApplyRepo->getApprovedAmountTotals($startDateTime, $endDateTime, $customerType);
            $ibCommissionAmount = $this->ibCommissionSettlementRepo->getCommissionAmount($startDateTime, $endDateTime);
            $ibCommissionCustomerCount = $this->ibCommissionSettlementRepo->getCommissionCustomerCount($startDateTime, $endDateTime);
            $masterCommissionAmount = $this->masterCommissionRecordRepo->getSettledCommissionAmount($startDateTime, $endDateTime);
            $masterCommissionCustomerCount = $this->masterCommissionRecordRepo->getSettledCommissionCustomerCount($startDateTime, $endDateTime);

            $registeredByPeriod = $this->userRepo->getRegisteredUserCountByPeriod($startDateTime, $endDateTime, $customerType, $chartDateType);
            $firstDepositByPeriod = $this->fundingAccountRepo->getFirstDepositUserCountByPeriod($startDateTime, $endDateTime, $customerType, $chartDateType);
            $amountByPeriod = $this->fundingApplyRepo->getApprovedAmountTotalsByPeriod($startDateTime, $endDateTime, $customerType, $chartDateType);
        } else {
            $registered_Users = $this->tradeAccountRepo->getRegisteredBonusFundCount($startDateTime, $endDateTime, $customerType);
            $firstDepositUsers = 0;
            $amountTotals = [
                'deposit_amount'    => 0.0,
                'withdrawal_amount' => 0.0,
                'net_deposit_amount' => 0.0,
            ];
            $ibCommissionAmount = 0.0;
            $ibCommissionCustomerCount = 0;
            $masterCommissionAmount = 0.0;
            $masterCommissionCustomerCount = 0;

            $registeredByPeriod = $this->tradeAccountRepo->getRegisteredBonusFundCountByPeriod($startDateTime, $endDateTime, $customerType, $chartDateType);
            $firstDepositByPeriod = [];
            $amountByPeriod = [];
        }

        $tradingUsers     = $this->tradeRepo->getTradingUserCount($startDateTime, $endDateTime, $tradeAccountType, $customerType);
        $tradingStats     = $this->tradeRepo->getTradingStats($startDateTime, $endDateTime, $tradeAccountType, $customerType);
        $sourceTradingRank = $this->tradeRepo->getSourceTradingRank($startDateTime, $endDateTime, $sourceTradingType, $tradeAccountType, $customerType);
        $orderCountsBySourceType = $this->tradeRepo->getOrderCountsBySourceType($startDateTime, $endDateTime, $tradeAccountType, $customerType);

        $tradingUsersByPeriod = $this->tradeRepo->getTradingUserCountByPeriod($startDateTime, $endDateTime, $tradeAccountType, $customerType, '', $chartDateType);
        $tradingStatsByPeriod = $this->tradeRepo->getTradingStatsByPeriod($startDateTime, $endDateTime, $tradeAccountType, $customerType, '', $chartDateType);

        return [
            'registered_users'      => $registered_Users,
            'first_deposit_users'   => $firstDepositUsers,
            'trading_users'         => $tradingUsers,
            'trading_lots'          => $tradingStats['lots'],
            'trading_amount'        => $tradingStats['amount'],
            'order_count'           => $tradingStats['order_count'],
            'deposit_amount'        => $amountTotals['deposit_amount'],
            'withdrawal_amount'     => $amountTotals['withdrawal_amount'],
            'net_deposit_amount'    => $amountTotals['net_deposit_amount'],
            'ib_commission_amount'  => $ibCommissionAmount,
            'ib_commission_customer_count' => $ibCommissionCustomerCount,
            'master_commission_amount' => $masterCommissionAmount,
            'master_commission_customer_count' => $masterCommissionCustomerCount,
            'source_trading_rank'  => $sourceTradingRank,
            'source_type_order_count_distribution' => $this->buildSourceTypeOrderCountDistribution($orderCountsBySourceType),
            'chart'                 => $this->buildChartSeries(
                $chartDateType,
                $dateStart,
                $dateEnd,
                $registeredByPeriod,
                $firstDepositByPeriod,
                $tradingUsersByPeriod,
                $tradingStatsByPeriod,
                $amountByPeriod
            ),
        ];
    }

    /**
     * 将各指标的按期间统计拆分为 chart 分组下的独立列表，范围内无数据的期间补 0
     *
     * 期间按 $chartDateType 粒度对齐：日为当天、周为所在周的周一（ISO）、月为所在月首日，
     * 与各仓库 periodExpression 生成的期间起始日期一致。
     *
     * @param string                                               $chartDateType
     * @param string                                               $dateStart
     * @param string                                               $dateEnd
     * @param array<string, int>                                   $registeredByPeriod
     * @param array<string, int>                                   $firstDepositByPeriod
     * @param array<string, int>                                   $tradingUsersByPeriod
     * @param array<string, array{lots:float, amount:float, order_count:int}> $tradingStatsByPeriod
     * @param array<string, array{deposit_amount:float, withdrawal_amount:float, net_deposit_amount:float}> $amountByPeriod
     *
     * @return array{daily_registered_users:array<int, array{date:string, value:int}>, daily_first_deposit_users:array<int, array{date:string, value:int}>, daily_trading_users:array<int, array{date:string, value:int}>, daily_trading_lots:array<int, array{date:string, value:float}>, daily_trading_amount:array<int, array{date:string, value:float}>, daily_order_count:array<int, array{date:string, value:int}>, daily_deposit_amount:array<int, array{date:string, value:float}>, daily_withdrawal_amount:array<int, array{date:string, value:float}>, daily_net_deposit_amount:array<int, array{date:string, value:float}>}
     */
    private function buildChartSeries(
        string $chartDateType,
        string $dateStart,
        string $dateEnd,
        array $registeredByPeriod,
        array $firstDepositByPeriod,
        array $tradingUsersByPeriod,
        array $tradingStatsByPeriod,
        array $amountByPeriod
    ): array {
        $dailySeries = [
            'daily_registered_users'    => [],
            'daily_first_deposit_users' => [],
            'daily_trading_users'       => [],
            'daily_trading_lots'        => [],
            'daily_trading_amount'      => [],
            'daily_order_count'         => [],
            'daily_deposit_amount'      => [],
            'daily_withdrawal_amount'   => [],
            'daily_net_deposit_amount'  => [],
        ];

        foreach ($this->chartPeriodDates($chartDateType, $dateStart, $dateEnd) as $date) {
            $dailySeries['daily_registered_users'][]    = ['date' => $date, 'value' => (int) ($registeredByPeriod[$date] ?? 0)];
            $dailySeries['daily_first_deposit_users'][] = ['date' => $date, 'value' => (int) ($firstDepositByPeriod[$date] ?? 0)];
            $dailySeries['daily_trading_users'][]       = ['date' => $date, 'value' => (int) ($tradingUsersByPeriod[$date] ?? 0)];
            $dailySeries['daily_trading_lots'][]        = ['date' => $date, 'value' => (float) ($tradingStatsByPeriod[$date]['lots'] ?? 0)];
            $dailySeries['daily_trading_amount'][]      = ['date' => $date, 'value' => (float) ($tradingStatsByPeriod[$date]['amount'] ?? 0)];
            $dailySeries['daily_order_count'][]         = ['date' => $date, 'value' => (int) ($tradingStatsByPeriod[$date]['order_count'] ?? 0)];
            $dailySeries['daily_deposit_amount'][]      = ['date' => $date, 'value' => (float) ($amountByPeriod[$date]['deposit_amount'] ?? 0)];
            $dailySeries['daily_withdrawal_amount'][]   = ['date' => $date, 'value' => (float) ($amountByPeriod[$date]['withdrawal_amount'] ?? 0)];
            $dailySeries['daily_net_deposit_amount'][]  = ['date' => $date, 'value' => (float) ($amountByPeriod[$date]['net_deposit_amount'] ?? 0)];
        }

        return $dailySeries;
    }

    /**
     * @param array<string, int> $orderCountsBySourceType
     * @return array<int, array{source_type:string, order_count:int, percentage:float}>
     */
    private function buildSourceTypeOrderCountDistribution(array $orderCountsBySourceType): array
    {
        $sourceTypes = [
            TsSource::METAL,
            TsSource::INDEX,
            TsSource::CRYPTO,
            TsSource::FOREX,
            TsSource::ENERGY,
        ];
        $totalOrderCount = array_sum(array_map(
            static fn (string $sourceType): int => (int) ($orderCountsBySourceType[$sourceType] ?? 0),
            $sourceTypes
        ));

        return array_map(static function (string $sourceType) use ($orderCountsBySourceType, $totalOrderCount): array {
            $orderCount = (int) ($orderCountsBySourceType[$sourceType] ?? 0);

            return [
                'source_type' => $sourceType,
                'order_count' => $orderCount,
                'percentage' => $totalOrderCount === 0 ? 0.0 : round($orderCount / $totalOrderCount * 100, 2),
            ];
        }, $sourceTypes);
    }

    /**
     * 枚举时间范围覆盖的全部期间起始日期（Y-m-d），与仓库 periodExpression 的期间口径一致：
     * - day：范围内每天
     * - week：范围首尾所在周（周一起始）的每周一
     * - month：范围首尾所在月的每月首日
     *
     * @return array<int, string>
     */
    private function chartPeriodDates(string $chartDateType, string $dateStart, string $dateEnd): array
    {
        $current = new \DateTime($dateStart);
        $end     = new \DateTime($dateEnd);

        if ($chartDateType === DataOverviewRequest::CHART_DATE_TYPE_WEEK) {
            // format('N')：1=周一 … 7=周日，对齐到所在周周一
            $current->modify('-' . ((int) $current->format('N') - 1) . ' days');
            $end->modify('-' . ((int) $end->format('N') - 1) . ' days');
        } elseif ($chartDateType === DataOverviewRequest::CHART_DATE_TYPE_MONTH) {
            $current->modify('first day of this month');
            $end->modify('first day of this month');
        }

        $step = match ($chartDateType) {
            DataOverviewRequest::CHART_DATE_TYPE_WEEK  => '+7 days',
            DataOverviewRequest::CHART_DATE_TYPE_MONTH => '+1 month',
            default                                    => '+1 day',
        };

        $dates = [];
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify($step);
        }

        return $dates;
    }

    /**
     * @return array{registered_users:int, first_deposit_users:int, trading_users:int, trading_lots:float, trading_amount:float, order_count:int, deposit_amount:float, withdrawal_amount:float, net_deposit_amount:float, ib_commission_amount:float, ib_commission_customer_count:int, master_commission_amount:float, master_commission_customer_count:int, source_trading_rank:array, source_type_order_count_distribution:array, chart:array{daily_registered_users:array, daily_first_deposit_users:array, daily_trading_users:array, daily_trading_lots:array, daily_trading_amount:array, daily_order_count:array, daily_deposit_amount:array, daily_withdrawal_amount:array, daily_net_deposit_amount:array}}
     */
    private function emptyOverview(): array
    {
        return [
            'registered_users'          => 0,
            'first_deposit_users'       => 0,
            'trading_users'             => 0,
            'trading_lots'              => 0.0,
            'trading_amount'            => 0.0,
            'order_count'               => 0,
            'deposit_amount'            => 0.0,
            'withdrawal_amount'         => 0.0,
            'net_deposit_amount'        => 0.0,
            'ib_commission_amount'      => 0.0,
            'ib_commission_customer_count' => 0,
            'master_commission_amount'     => 0.0,
            'master_commission_customer_count' => 0,
            'source_trading_rank'       => [],
            'source_type_order_count_distribution' => [],
            'chart'                     => [
                'daily_registered_users'    => [],
                'daily_first_deposit_users' => [],
                'daily_trading_users'       => [],
                'daily_trading_lots'        => [],
                'daily_trading_amount'      => [],
                'daily_order_count'         => [],
                'daily_deposit_amount'      => [],
                'daily_withdrawal_amount'   => [],
                'daily_net_deposit_amount'  => [],
            ],
        ];
    }
}
