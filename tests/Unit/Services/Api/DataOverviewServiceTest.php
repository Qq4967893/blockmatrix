<?php

namespace Tests\Unit\Services\Api;

use App\Http\Requests\DataOverview\DataOverviewRequest;
use App\Repositories\Api\FundingAccountRepository;
use App\Repositories\Api\FundingApplyRepository;
use App\Repositories\Api\IbCommissionSettlementRepository;
use App\Repositories\Api\MasterCommissionRecordRepository;
use App\Repositories\Api\TradeAccountRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Et6\TradeRepository;
use App\Services\Api\DataOverviewService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class DataOverviewServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_real_overview_passes_customer_type_and_source_trading_type_through(): void
    {
        [$userRepo, $tradeAccountRepo, $fundingAccountRepo, $fundingApplyRepo, $ibCommissionSettlementRepo, $masterCommissionRecordRepo, $tradeRepo] = $this->mockRepositories();

        $userRepo->shouldReceive('getRegisteredUserCount')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_AGENT)
            ->andReturn(3);
        $userRepo->shouldReceive('getRegisteredUserCountByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_AGENT, 'day')
            ->andReturn(['2026-09-01' => 3]);
        $fundingAccountRepo->shouldReceive('getFirstDepositUserCount')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_AGENT)
            ->andReturn(2);
        $fundingAccountRepo->shouldReceive('getFirstDepositUserCountByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_AGENT, 'day')
            ->andReturn(['2026-09-01' => 2]);
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotals')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_AGENT)
            ->andReturn([
                'deposit_amount'    => 1000.5,
                'withdrawal_amount' => 200.25,
                'net_deposit_amount' => 800.25,
            ]);
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotalsByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_AGENT, 'day')
            ->andReturn(['2026-09-01' => ['deposit_amount' => 1000.5, 'withdrawal_amount' => 200.25, 'net_deposit_amount' => 800.25]]);
        $ibCommissionSettlementRepo->shouldReceive('getCommissionAmount')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59')
            ->andReturn(123.45);
        $ibCommissionSettlementRepo->shouldReceive('getCommissionCustomerCount')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59')
            ->andReturn(6);
        $masterCommissionRecordRepo->shouldReceive('getSettledCommissionAmount')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59')
            ->andReturn(45.67);
        $masterCommissionRecordRepo->shouldReceive('getSettledCommissionCustomerCount')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59')
            ->andReturn(4);
        $tradeRepo->shouldReceive('getTradingUserCount')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_AGENT)
            ->andReturn(1);
        $tradeRepo->shouldReceive('getTradingUserCountByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_AGENT, '', 'day')
            ->andReturn(['2026-09-01' => 1]);
        $tradeRepo->shouldReceive('getTradingStats')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_AGENT)
            ->andReturn(['lots' => 12.5, 'amount' => 3000.0, 'order_count' => 30]);
        $tradeRepo->shouldReceive('getTradingStatsByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_AGENT, '', 'day')
            ->andReturn(['2026-09-01' => ['lots' => 12.5, 'amount' => 3000.0, 'order_count' => 30]]);
        $tradeRepo->shouldReceive('getSourceTradingRank')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'lot', 'real', DataOverviewRequest::CUSTOMER_TYPE_AGENT)
            ->andReturn([['source' => 'BTCUSDT', 'value' => 12.5]]);
        $tradeRepo->shouldReceive('getOrderCountsBySourceType')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_AGENT)
            ->andReturn([
                'metal' => 20,
                'index' => 10,
                'crypto' => 30,
                'forex' => 40,
                'energy' => 0,
            ]);

        $service = new DataOverviewService($userRepo, $tradeAccountRepo, $fundingAccountRepo, $fundingApplyRepo, $ibCommissionSettlementRepo, $masterCommissionRecordRepo, $tradeRepo);

        $result = $service->getOverview(
            '2026-09-01',
            '2026-09-10',
            'real',
            DataOverviewRequest::CUSTOMER_TYPE_AGENT,
            'lot',
            'day'
        );

        self::assertSame(3, $result['registered_users']);
        self::assertSame(2, $result['first_deposit_users']);
        self::assertSame(1, $result['trading_users']);
        self::assertSame(12.5, $result['trading_lots']);
        self::assertSame(3000.0, $result['trading_amount']);
        self::assertSame(30, $result['order_count']);
        self::assertSame(1000.5, $result['deposit_amount']);
        self::assertSame(200.25, $result['withdrawal_amount']);
        self::assertSame(800.25, $result['net_deposit_amount']);
        self::assertSame(123.45, $result['ib_commission_amount']);
        self::assertSame(6, $result['ib_commission_customer_count']);
        self::assertSame(45.67, $result['master_commission_amount']);
        self::assertSame(4, $result['master_commission_customer_count']);
        self::assertSame([['source' => 'BTCUSDT', 'value' => 12.5]], $result['source_trading_rank']);
        $sourceTypeDistribution = $result['source_type_order_count_distribution'];
        self::assertSame(['metal', 'index', 'crypto', 'forex', 'energy'], array_column($sourceTypeDistribution, 'source_type'));
        self::assertSame([20, 10, 30, 40, 0], array_column($sourceTypeDistribution, 'order_count'));
        self::assertSame([20.0, 10.0, 30.0, 40.0, 0.0], array_column($sourceTypeDistribution, 'percentage'));

        // 各指标独立逐日列表，覆盖范围内每一天，无数据的日期补 0
        self::assertSame(['date' => '2026-09-01', 'value' => 3], $result['chart']['daily_registered_users'][0]);
        self::assertSame(['date' => '2026-09-02', 'value' => 0], $result['chart']['daily_registered_users'][1]);
        self::assertSame(['date' => '2026-09-01', 'value' => 2], $result['chart']['daily_first_deposit_users'][0]);
        self::assertSame(['date' => '2026-09-01', 'value' => 1], $result['chart']['daily_trading_users'][0]);
        self::assertSame(['date' => '2026-09-01', 'value' => 12.5], $result['chart']['daily_trading_lots'][0]);
        self::assertSame(['date' => '2026-09-01', 'value' => 3000.0], $result['chart']['daily_trading_amount'][0]);
        self::assertSame(['date' => '2026-09-01', 'value' => 30], $result['chart']['daily_order_count'][0]);
        self::assertSame(['date' => '2026-09-01', 'value' => 1000.5], $result['chart']['daily_deposit_amount'][0]);
        self::assertSame(['date' => '2026-09-01', 'value' => 200.25], $result['chart']['daily_withdrawal_amount'][0]);
        self::assertSame(['date' => '2026-09-01', 'value' => 800.25], $result['chart']['daily_net_deposit_amount'][0]);

        foreach ([
            'daily_registered_users',
            'daily_first_deposit_users',
            'daily_trading_users',
            'daily_trading_lots',
            'daily_trading_amount',
            'daily_order_count',
            'daily_deposit_amount',
            'daily_withdrawal_amount',
            'daily_net_deposit_amount',
        ] as $seriesKey) {
            self::assertCount(10, $result['chart'][$seriesKey]);
            self::assertSame('2026-09-10', $result['chart'][$seriesKey][9]['date']);
        }
    }

    public function test_trial_overview_returns_zero_funding_amounts_without_funding_queries(): void
    {
        [$userRepo, $tradeAccountRepo, $fundingAccountRepo, $fundingApplyRepo, $ibCommissionSettlementRepo, $masterCommissionRecordRepo, $tradeRepo] = $this->mockRepositories();

        $tradeAccountRepo->shouldReceive('getRegisteredBonusFundCount')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL)
            ->andReturn(5);
        $tradeAccountRepo->shouldReceive('getRegisteredBonusFundCountByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'day')
            ->andReturn(['2026-09-01' => 5]);
        // 赠金账户无入出金概念，不应触发资金查询
        $fundingAccountRepo->shouldReceive('getFirstDepositUserCount')->never();
        $fundingAccountRepo->shouldReceive('getFirstDepositUserCountByPeriod')->never();
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotals')->never();
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotalsByPeriod')->never();
        $tradeRepo->shouldReceive('getTradingUserCount')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'trial', DataOverviewRequest::CUSTOMER_TYPE_ALL)
            ->andReturn(4);
        $tradeRepo->shouldReceive('getTradingUserCountByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'trial', DataOverviewRequest::CUSTOMER_TYPE_ALL, '', 'day')
            ->andReturn(['2026-09-01' => 4]);
        $tradeRepo->shouldReceive('getTradingStats')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'trial', DataOverviewRequest::CUSTOMER_TYPE_ALL)
            ->andReturn(['lots' => 6.0, 'amount' => 500.0, 'order_count' => 8]);
        $tradeRepo->shouldReceive('getTradingStatsByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'trial', DataOverviewRequest::CUSTOMER_TYPE_ALL, '', 'day')
            ->andReturn(['2026-09-01' => ['lots' => 6.0, 'amount' => 500.0, 'order_count' => 8]]);
        $tradeRepo->shouldReceive('getSourceTradingRank')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'amount', 'trial', DataOverviewRequest::CUSTOMER_TYPE_ALL)
            ->andReturn([]);

        $service = new DataOverviewService($userRepo, $tradeAccountRepo, $fundingAccountRepo, $fundingApplyRepo, $ibCommissionSettlementRepo, $masterCommissionRecordRepo, $tradeRepo);

        $result = $service->getOverview(
            '2026-09-01',
            '2026-09-10',
            'trial',
            DataOverviewRequest::CUSTOMER_TYPE_ALL,
            'amount',
            'day'
        );

        self::assertSame(5, $result['registered_users']);
        self::assertSame(0, $result['first_deposit_users']);
        self::assertSame(4, $result['trading_users']);
        self::assertSame(6.0, $result['trading_lots']);
        self::assertSame(8, $result['order_count']);
        self::assertSame(0.0, $result['deposit_amount']);
        self::assertSame(0.0, $result['withdrawal_amount']);
        self::assertSame(0.0, $result['net_deposit_amount']);
        self::assertSame(0.0, $result['ib_commission_amount']);
        self::assertSame(0, $result['ib_commission_customer_count']);
        self::assertSame(0.0, $result['master_commission_amount']);
        self::assertSame(0, $result['master_commission_customer_count']);

        // trial 逐日列表：交易指标有值，资金指标补 0
        self::assertSame(['date' => '2026-09-01', 'value' => 5], $result['chart']['daily_registered_users'][0]);
        self::assertSame(['date' => '2026-09-01', 'value' => 0], $result['chart']['daily_first_deposit_users'][0]);
        self::assertSame(['date' => '2026-09-01', 'value' => 4], $result['chart']['daily_trading_users'][0]);
        self::assertSame(['date' => '2026-09-01', 'value' => 6.0], $result['chart']['daily_trading_lots'][0]);
        self::assertSame(['date' => '2026-09-01', 'value' => 0.0], $result['chart']['daily_deposit_amount'][0]);
        self::assertSame(['date' => '2026-09-01', 'value' => 0.0], $result['chart']['daily_net_deposit_amount'][0]);
    }

    public function test_trial_overview_with_agent_customer_type_returns_empty_without_queries(): void
    {
        [$userRepo, $tradeAccountRepo, $fundingAccountRepo, $fundingApplyRepo, $ibCommissionSettlementRepo, $masterCommissionRecordRepo, $tradeRepo] = $this->mockRepositories();

        // 赠金账户 + 代理客户组合无业务意义，不应触发任何查询
        $userRepo->shouldReceive('getRegisteredUserCount')->never();
        $userRepo->shouldReceive('getRegisteredUserCountByPeriod')->never();
        $tradeAccountRepo->shouldReceive('getRegisteredBonusFundCount')->never();
        $tradeAccountRepo->shouldReceive('getRegisteredBonusFundCountByPeriod')->never();
        $fundingAccountRepo->shouldReceive('getFirstDepositUserCount')->never();
        $fundingAccountRepo->shouldReceive('getFirstDepositUserCountByPeriod')->never();
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotals')->never();
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotalsByPeriod')->never();
        $tradeRepo->shouldReceive('getTradingUserCount')->never();
        $tradeRepo->shouldReceive('getTradingUserCountByPeriod')->never();
        $tradeRepo->shouldReceive('getTradingStats')->never();
        $tradeRepo->shouldReceive('getTradingStatsByPeriod')->never();
        $tradeRepo->shouldReceive('getSourceTradingRank')->never();

        $service = new DataOverviewService($userRepo, $tradeAccountRepo, $fundingAccountRepo, $fundingApplyRepo, $ibCommissionSettlementRepo, $masterCommissionRecordRepo, $tradeRepo);

        $result = $service->getOverview(
            '2026-09-01',
            '2026-09-10',
            'trial',
            DataOverviewRequest::CUSTOMER_TYPE_AGENT,
            'BTC'
        );

        self::assertSame([
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
        ], $result);
    }

    public function test_overview_defaults_to_all_customer_type_without_source_filter(): void
    {
        [$userRepo, $tradeAccountRepo, $fundingAccountRepo, $fundingApplyRepo, $ibCommissionSettlementRepo, $masterCommissionRecordRepo, $tradeRepo] = $this->mockRepositories();

        $userRepo->shouldReceive('getRegisteredUserCount')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL)
            ->andReturn(9);
        $userRepo->shouldReceive('getRegisteredUserCountByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'day')
            ->andReturn([]);
        $fundingAccountRepo->shouldReceive('getFirstDepositUserCount')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL)
            ->andReturn(8);
        $fundingAccountRepo->shouldReceive('getFirstDepositUserCountByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'day')
            ->andReturn([]);
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotals')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL)
            ->andReturn([
                'deposit_amount'    => 500.0,
                'withdrawal_amount' => 100.0,
                'net_deposit_amount' => 400.0,
            ]);
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotalsByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'day')
            ->andReturn([]);
        $tradeRepo->shouldReceive('getTradingUserCount')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_ALL)
            ->andReturn(7);
        $tradeRepo->shouldReceive('getTradingUserCountByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_ALL, '', 'day')
            ->andReturn([]);
        $tradeRepo->shouldReceive('getTradingStats')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_ALL)
            ->andReturn(['lots' => 0.0, 'amount' => 0.0, 'order_count' => 0]);
        $tradeRepo->shouldReceive('getTradingStatsByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_ALL, '', 'day')
            ->andReturn([]);
        $tradeRepo->shouldReceive('getSourceTradingRank')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'lot', 'real', DataOverviewRequest::CUSTOMER_TYPE_ALL)
            ->andReturn([]);

        $service = new DataOverviewService($userRepo, $tradeAccountRepo, $fundingAccountRepo, $fundingApplyRepo, $ibCommissionSettlementRepo, $masterCommissionRecordRepo, $tradeRepo);

        $result = $service->getOverview('2026-09-01', '2026-09-10', 'real', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'lot', 'day');

        self::assertSame(9, $result['registered_users']);
        self::assertSame(8, $result['first_deposit_users']);
        self::assertSame(7, $result['trading_users']);
        self::assertSame(400.0, $result['net_deposit_amount']);
        // 全部指标无每日数据时，仍返回范围内每天补 0 的独立列表
        self::assertCount(10, $result['chart']['daily_registered_users']);
        self::assertSame('2026-09-01', $result['chart']['daily_registered_users'][0]['date']);
        self::assertSame('2026-09-10', $result['chart']['daily_registered_users'][9]['date']);
        self::assertSame(0, $result['chart']['daily_registered_users'][0]['value']);
        self::assertSame(['date' => '2026-09-01', 'value' => 0.0], $result['chart']['daily_net_deposit_amount'][0]);
    }

    public function test_chart_week_granularity_groups_by_iso_week_start(): void
    {
        [$userRepo, $tradeAccountRepo, $fundingAccountRepo, $fundingApplyRepo, $ibCommissionSettlementRepo, $masterCommissionRecordRepo, $tradeRepo] = $this->mockRepositories();

        // 2026-09-01 是周二，2026-09-10 是周四：覆盖 08-31（周一）与 09-07（周一）两 周
        $userRepo->shouldReceive('getRegisteredUserCount')->once()->andReturn(3);
        $userRepo->shouldReceive('getRegisteredUserCountByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'week')
            ->andReturn(['2026-09-07' => 3]);
        $fundingAccountRepo->shouldReceive('getFirstDepositUserCount')->once()->andReturn(0);
        $fundingAccountRepo->shouldReceive('getFirstDepositUserCountByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'week')
            ->andReturn([]);
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotals')->once()
            ->andReturn(['deposit_amount' => 0.0, 'withdrawal_amount' => 0.0, 'net_deposit_amount' => 0.0]);
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotalsByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'week')
            ->andReturn([]);
        $tradeRepo->shouldReceive('getTradingUserCount')->once()->andReturn(0);
        $tradeRepo->shouldReceive('getTradingUserCountByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_ALL, '', 'week')
            ->andReturn([]);
        $tradeRepo->shouldReceive('getTradingStats')->once()
            ->andReturn(['lots' => 0.0, 'amount' => 0.0, 'order_count' => 0]);
        $tradeRepo->shouldReceive('getTradingStatsByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_ALL, '', 'week')
            ->andReturn([]);
        $tradeRepo->shouldReceive('getSourceTradingRank')->once()
            ->andReturn(['lot' => [], 'amount' => [], 'order_count' => []]);

        $service = new DataOverviewService($userRepo, $tradeAccountRepo, $fundingAccountRepo, $fundingApplyRepo, $ibCommissionSettlementRepo, $masterCommissionRecordRepo, $tradeRepo);

        $result = $service->getOverview('2026-09-01', '2026-09-10', 'real', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'lot', 'week');

        // 周粒度：两周（周一 08-31 / 09-07），无数据的周补 0
        self::assertSame([
            ['date' => '2026-08-31', 'value' => 0],
            ['date' => '2026-09-07', 'value' => 3],
        ], $result['chart']['daily_registered_users']);
    }

    public function test_chart_month_granularity_groups_by_month_start(): void
    {
        [$userRepo, $tradeAccountRepo, $fundingAccountRepo, $fundingApplyRepo, $ibCommissionSettlementRepo, $masterCommissionRecordRepo, $tradeRepo] = $this->mockRepositories();

        // 范围跨 8/9 两 月：期间为 08-01 与 09-01
        $userRepo->shouldReceive('getRegisteredUserCount')->once()->andReturn(1);
        $userRepo->shouldReceive('getRegisteredUserCountByPeriod')
            ->once()
            ->with('2026-08-20 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'month')
            ->andReturn(['2026-08-01' => 1]);
        $fundingAccountRepo->shouldReceive('getFirstDepositUserCount')->once()->andReturn(0);
        $fundingAccountRepo->shouldReceive('getFirstDepositUserCountByPeriod')
            ->once()
            ->with('2026-08-20 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'month')
            ->andReturn([]);
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotals')->once()
            ->andReturn(['deposit_amount' => 0.0, 'withdrawal_amount' => 0.0, 'net_deposit_amount' => 0.0]);
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotalsByPeriod')
            ->once()
            ->with('2026-08-20 00:00:00', '2026-09-10 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'month')
            ->andReturn([]);
        $tradeRepo->shouldReceive('getTradingUserCount')->once()->andReturn(0);
        $tradeRepo->shouldReceive('getTradingUserCountByPeriod')
            ->once()
            ->with('2026-08-20 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_ALL, '', 'month')
            ->andReturn([]);
        $tradeRepo->shouldReceive('getTradingStats')->once()
            ->andReturn(['lots' => 0.0, 'amount' => 0.0, 'order_count' => 0]);
        $tradeRepo->shouldReceive('getTradingStatsByPeriod')
            ->once()
            ->with('2026-08-20 00:00:00', '2026-09-10 23:59:59', 'real', DataOverviewRequest::CUSTOMER_TYPE_ALL, '', 'month')
            ->andReturn([]);
        $tradeRepo->shouldReceive('getSourceTradingRank')->once()
            ->andReturn([]);

        $service = new DataOverviewService($userRepo, $tradeAccountRepo, $fundingAccountRepo, $fundingApplyRepo, $ibCommissionSettlementRepo, $masterCommissionRecordRepo, $tradeRepo);

        $result = $service->getOverview('2026-08-20', '2026-09-10', 'real', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'lot', 'month');

        // 月粒度：两月（08-01 / 09-01），无数据的月补 0
        self::assertSame([
            ['date' => '2026-08-01', 'value' => 1],
            ['date' => '2026-09-01', 'value' => 0],
        ], $result['chart']['daily_registered_users']);
    }

    /**
     * @return array{0: UserRepository&MockInterface, 1: TradeAccountRepository&MockInterface, 2: FundingAccountRepository&MockInterface, 3: FundingApplyRepository&MockInterface, 4: IbCommissionSettlementRepository&MockInterface, 5: MasterCommissionRecordRepository&MockInterface, 6: TradeRepository&MockInterface}
     */
    private function mockRepositories(): array
    {
        return [
            Mockery::mock(UserRepository::class),
            Mockery::mock(TradeAccountRepository::class),
            Mockery::mock(FundingAccountRepository::class),
            Mockery::mock(FundingApplyRepository::class),
            Mockery::mock(IbCommissionSettlementRepository::class)
                ->shouldReceive('getCommissionAmount')->byDefault()->andReturn(0.0)
                ->getMock()
                ->shouldReceive('getCommissionCustomerCount')->byDefault()->andReturn(0)
                ->getMock(),
            Mockery::mock(MasterCommissionRecordRepository::class)
                ->shouldReceive('getSettledCommissionAmount')->byDefault()->andReturn(0.0)
                ->getMock()
                ->shouldReceive('getSettledCommissionCustomerCount')->byDefault()->andReturn(0)
                ->getMock(),
            Mockery::mock(TradeRepository::class)
                ->shouldReceive('getOrderCountsBySourceType')->byDefault()->andReturn([])
                ->getMock(),
        ];
    }
}
