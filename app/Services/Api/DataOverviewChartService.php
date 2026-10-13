<?php

namespace App\Services\Api;

use App\Http\Requests\DataOverview\DataOverviewChartRequest;
use App\Http\Requests\DataOverview\DataOverviewRequest;
use App\Repositories\Api\FundingAccountRepository;
use App\Repositories\Api\FundingApplyRepository;
use App\Repositories\Api\TradeAccountRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Et6\TradeRepository;

class DataOverviewChartService
{
    public function __construct(
        private UserRepository $userRepo,
        private TradeAccountRepository $tradeAccountRepo,
        private FundingAccountRepository $fundingAccountRepo,
        private FundingApplyRepository $fundingApplyRepo,
        private TradeRepository $tradeRepo,
    ) {
    }

    /**
     * 获取指定类型的数据概览图表序列。
     *
     * 仅查询 chartType 对应的一项指标；日期范围内无统计数据的期间补 0。
     * 试玩账户的首次入金及入出金相关图表固定返回 0；试玩账户的代理客户组合返回空列表。
     *
     * @param string $dateStart        开始日期，格式 Y-m-d
     * @param string $dateEnd          结束日期，格式 Y-m-d
     * @param string $tradeAccountType 账户类型：real / trial
     * @param string $customerType     客户类型：all / ib
     * @param string $chartDateType    日期粒度：day / week / month
     * @param string $chartType        图表类型，取值见 DataOverviewChartRequest::CHART_TYPES
     *
     * @return array<int, array{date:string, value:int|float}>
     */
    public function getChartSeries(string $dateStart, string $dateEnd, string $tradeAccountType, string $customerType, string $chartDateType, string $chartType): array
    {
        if ($tradeAccountType === DataOverviewRequest::TRADE_ACCOUNT_TYPE_TRIAL
            && $customerType === DataOverviewRequest::CUSTOMER_TYPE_AGENT) {
            return [];
        }

        $startDateTime = $dateStart . ' 00:00:00';
        $endDateTime   = $dateEnd . ' 23:59:59';
        $periodValues  = $this->getPeriodValues($startDateTime, $endDateTime, $tradeAccountType, $customerType, $chartDateType, $chartType);
        $valueType     = in_array($chartType, [
            'daily_registered_users',
            'daily_first_deposit_users',
            'daily_trading_users',
            'daily_order_count',
        ], true) ? 'int' : 'float';

        return array_map(static function (string $date) use ($periodValues, $valueType): array {
            $value = $periodValues[$date] ?? 0;

            return [
                'date' => $date,
                'value' => $valueType === 'int' ? (int) $value : (float) $value,
            ];
        }, $this->chartPeriodDates($chartDateType, $dateStart, $dateEnd));
    }

    /**
     * 查询指定图表类型按期间聚合的原始数值。
     *
     * @param string $startDateTime    开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime      结束时间，格式 Y-m-d H:i:s
     * @param string $tradeAccountType 账户类型：real / trial
     * @param string $customerType     客户类型：all / ib
     * @param string $chartDateType    日期粒度：day / week / month
     * @param string $chartType        图表类型
     *
     * @return array<string, int|float> key 为期间起始日期（Y-m-d）
     */
    private function getPeriodValues(string $startDateTime, string $endDateTime, string $tradeAccountType, string $customerType, string $chartDateType, string $chartType): array
    {
        if ($chartType === 'daily_registered_users') {
            return $tradeAccountType === DataOverviewRequest::TRADE_ACCOUNT_TYPE_REAL
                ? $this->userRepo->getRegisteredUserCountByPeriod($startDateTime, $endDateTime, $customerType, $chartDateType)
                : $this->tradeAccountRepo->getRegisteredBonusFundCountByPeriod($startDateTime, $endDateTime, $customerType, $chartDateType);
        }

        if ($chartType === 'daily_first_deposit_users') {
            return $tradeAccountType === DataOverviewRequest::TRADE_ACCOUNT_TYPE_REAL
                ? $this->fundingAccountRepo->getFirstDepositUserCountByPeriod($startDateTime, $endDateTime, $customerType, $chartDateType)
                : [];
        }

        if ($chartType === 'daily_trading_users') {
            return $this->tradeRepo->getTradingUserCountByPeriod($startDateTime, $endDateTime, $tradeAccountType, $customerType, '', $chartDateType);
        }

        if (in_array($chartType, ['daily_deposit_amount', 'daily_withdrawal_amount', 'daily_net_deposit_amount'], true)) {
            if ($tradeAccountType === DataOverviewRequest::TRADE_ACCOUNT_TYPE_TRIAL) {
                return [];
            }

            $amounts = $this->fundingApplyRepo->getApprovedAmountTotalsByPeriod($startDateTime, $endDateTime, $customerType, $chartDateType);

            return array_map(static fn (array $amount): float => (float) ($amount[substr($chartType, 6)] ?? 0), $amounts);
        }

        $stats = $this->tradeRepo->getTradingStatsByPeriod($startDateTime, $endDateTime, $tradeAccountType, $customerType, '', $chartDateType);
        $statKey = match ($chartType) {
            'daily_trading_lots' => 'lots',
            'daily_trading_amount' => 'amount',
            default => 'order_count',
        };

        return array_map(static fn (array $stat): int|float => $statKey === 'order_count'
            ? (int) ($stat[$statKey] ?? 0)
            : (float) ($stat[$statKey] ?? 0), $stats);
    }

    /**
     * 枚举日期范围内的全部图表期间起始日期。
     *
     * 周以周一为起始日，月以每月一日为起始日，与仓储统计的分组口径一致。
     *
     * @param string $chartDateType 日期粒度：day / week / month
     * @param string $dateStart     开始日期，格式 Y-m-d
     * @param string $dateEnd       结束日期，格式 Y-m-d
     *
     * @return array<int, string>
     */
    private function chartPeriodDates(string $chartDateType, string $dateStart, string $dateEnd): array
    {
        $current = new \DateTime($dateStart);
        $end = new \DateTime($dateEnd);

        if ($chartDateType === DataOverviewRequest::CHART_DATE_TYPE_WEEK) {
            $current->modify('-' . ((int) $current->format('N') - 1) . ' days');
            $end->modify('-' . ((int) $end->format('N') - 1) . ' days');
        } elseif ($chartDateType === DataOverviewRequest::CHART_DATE_TYPE_MONTH) {
            $current->modify('first day of this month');
            $end->modify('first day of this month');
        }

        $step = match ($chartDateType) {
            DataOverviewRequest::CHART_DATE_TYPE_WEEK => '+7 days',
            DataOverviewRequest::CHART_DATE_TYPE_MONTH => '+1 month',
            default => '+1 day',
        };
        $dates = [];
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify($step);
        }

        return $dates;
    }
}
