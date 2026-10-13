<?php

namespace Tests\Unit\Services\Api;

use App\Constants\BalanceFlowFeeCategory;
use App\Repositories\Api\BalanceFlowRepository;
use App\Repositories\Api\TradeAccountRepository;
use App\Services\Api\BalanceFlowService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class BalanceFlowServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_type_options_follow_scaffold_fee_categories(): void
    {
        app()->setLocale('zh_CN');

        $service = new BalanceFlowService(
            Mockery::mock(BalanceFlowRepository::class),
            Mockery::mock(TradeAccountRepository::class),
        );

        self::assertSame([
            ['code' => 3, 'name' => '结算'],
            [
                'code' => 4,
                'name' => '入金/出金',
                'sub_types' => [
                    ['code' => 17, 'name' => '信用赠金解锁'],
                ],
            ],
            ['code' => 5, 'name' => '分红'],
            ['code' => 6, 'name' => '穿仓补偿'],
            ['code' => 8, 'name' => '交易手续费'],
        ], $service->getTypeOptions());
    }

    public function test_list_returns_fund_type_text_instead_of_fee_category_text(): void
    {
        $tradeAccountRepo = Mockery::mock(TradeAccountRepository::class);
        $tradeAccountRepo->shouldReceive('getAccountsByUserCode')
            ->once()
            ->with(395894046)
            ->andReturn([['trade_account_id' => 10001]]);

        $balanceFlowRepo = Mockery::mock(BalanceFlowRepository::class);
        $balanceFlowRepo->shouldReceive('paginateByLogins')
            ->once()
            ->with([10001], Mockery::type('array'), 20, 0)
            ->andReturn([[
                [
                    'id' => 1,
                    'login' => 10001,
                    'fee_category' => BalanceFlowFeeCategory::DIVIDEND,
                    'fee_category_sub' => 0,
                    'fee_category_text' => '分红',
                    'amount' => -1,
                    'balance' => 99,
                ],
            ], 1]);

        $result = (new BalanceFlowService($balanceFlowRepo, $tradeAccountRepo))
            ->getList(395894046, [], 20, 0);

        self::assertSame('分红', $result['data'][0]['fund_type_text']);
        self::assertSame(0, $result['data'][0]['fund_sub_type']);
        self::assertSame(100.0, $result['data'][0]['bonus_balance']);
        self::assertArrayNotHasKey('fee_category_text', $result['data'][0]);
    }
}
