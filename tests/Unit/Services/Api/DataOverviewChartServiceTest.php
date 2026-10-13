<?php

namespace Tests\Unit\Services\Api;

use App\Http\Requests\DataOverview\DataOverviewRequest;
use App\Repositories\Api\FundingAccountRepository;
use App\Repositories\Api\FundingApplyRepository;
use App\Repositories\Api\TradeAccountRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Et6\TradeRepository;
use App\Services\Api\DataOverviewChartService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class DataOverviewChartServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_returns_only_requested_chart_series_and_fills_missing_periods(): void
    {
        $fundingApplyRepo = Mockery::mock(FundingApplyRepository::class);
        $fundingApplyRepo->shouldReceive('getApprovedAmountTotalsByPeriod')
            ->once()
            ->with('2026-09-01 00:00:00', '2026-09-03 23:59:59', DataOverviewRequest::CUSTOMER_TYPE_ALL, 'day')
            ->andReturn([
                '2026-09-01' => ['deposit_amount' => 50.5, 'withdrawal_amount' => 0.0, 'net_deposit_amount' => 50.5],
                '2026-09-03' => ['deposit_amount' => 20.0, 'withdrawal_amount' => 0.0, 'net_deposit_amount' => 20.0],
            ]);

        $service = new DataOverviewChartService(
            Mockery::mock(UserRepository::class),
            Mockery::mock(TradeAccountRepository::class),
            Mockery::mock(FundingAccountRepository::class),
            $fundingApplyRepo,
            Mockery::mock(TradeRepository::class),
        );

        self::assertSame([
            ['date' => '2026-09-01', 'value' => 50.5],
            ['date' => '2026-09-02', 'value' => 0.0],
            ['date' => '2026-09-03', 'value' => 20.0],
        ], $service->getChartSeries(
            '2026-09-01',
            '2026-09-03',
            DataOverviewRequest::TRADE_ACCOUNT_TYPE_REAL,
            DataOverviewRequest::CUSTOMER_TYPE_ALL,
            DataOverviewRequest::CHART_DATE_TYPE_DAY,
            'daily_deposit_amount',
        ));
    }

    public function test_returns_empty_series_for_trial_agent_combination(): void
    {
        $service = new DataOverviewChartService(
            Mockery::mock(UserRepository::class),
            Mockery::mock(TradeAccountRepository::class),
            Mockery::mock(FundingAccountRepository::class),
            Mockery::mock(FundingApplyRepository::class),
            Mockery::mock(TradeRepository::class),
        );

        self::assertSame([], $service->getChartSeries(
            '2026-09-01',
            '2026-09-03',
            DataOverviewRequest::TRADE_ACCOUNT_TYPE_TRIAL,
            DataOverviewRequest::CUSTOMER_TYPE_AGENT,
            DataOverviewRequest::CHART_DATE_TYPE_DAY,
            'daily_trading_users',
        ));
    }
}
