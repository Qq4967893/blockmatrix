<?php

namespace Tests\Unit;

use App\Http\Requests\DataOverview\DataOverviewRequest;
use PHPUnit\Framework\TestCase;

class DataOverviewRequestTest extends TestCase
{
    public function test_trial_trade_account_type_is_supported(): void
    {
        self::assertContains(
            DataOverviewRequest::TRADE_ACCOUNT_TYPE_TRIAL,
            DataOverviewRequest::TRADE_ACCOUNT_TYPES
        );
    }

    public function test_only_real_and_trial_trade_account_types_are_supported(): void
    {
        self::assertSame([
            DataOverviewRequest::TRADE_ACCOUNT_TYPE_REAL,
            DataOverviewRequest::TRADE_ACCOUNT_TYPE_TRIAL,
        ], DataOverviewRequest::TRADE_ACCOUNT_TYPES);
    }

    public function test_agent_customer_type_is_supported(): void
    {
        self::assertContains(
            DataOverviewRequest::CUSTOMER_TYPE_AGENT,
            DataOverviewRequest::CUSTOMER_TYPES
        );
    }

    public function test_only_all_and_agent_customer_types_are_supported(): void
    {
        self::assertSame([
            DataOverviewRequest::CUSTOMER_TYPE_ALL,
            DataOverviewRequest::CUSTOMER_TYPE_AGENT,
        ], DataOverviewRequest::CUSTOMER_TYPES);
    }

    public function test_source_trading_type_supports_all_ranking_dimensions(): void
    {
        self::assertSame([
            DataOverviewRequest::TRADING_TYPE_LOT,
            DataOverviewRequest::TRADING_TYPE_AMOUNT,
            DataOverviewRequest::TRADING_TYPE_ORDER_COUNT,
        ], DataOverviewRequest::TRADING_TYPES);
    }
}
