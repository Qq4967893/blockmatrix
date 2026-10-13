<?php

namespace Tests\Unit\Http\Controllers\Api;

use App\Http\Controllers\Api\UserInfoController;
use App\Http\Requests\CreditFlow\CreditFlowListRequest;
use App\Services\Api\CreditFlowService;
use App\Services\Api\UserService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class UserInfoControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_info_uses_bonus_fund_flag_from_user_overview(): void
    {
        $userCode = 780889597;
        $userService = Mockery::mock(UserService::class);
        $userService->shouldReceive('getOverviewInfo')
            ->once()
            ->with($userCode)
            ->andReturn([
                'user_code' => $userCode,
                'has_open_bonus_fund' => false,
            ]);

        $response = (new UserInfoController($userService, Mockery::mock(CreditFlowService::class)))->info($userCode);

        self::assertFalse($response->getData(true)['data']['has_open_bonus_fund']);
    }

    public function test_trade_accounts_parses_comma_separated_account_types(): void
    {
        $userCode = 123456789;
        $userService = Mockery::mock(UserService::class);
        $userService->shouldReceive('getByUserCode')
            ->once()
            ->with($userCode, ['user_code'])
            ->andReturn(['user_code' => $userCode]);
        $userService->shouldReceive('getUserTradeAccounts')
            ->once()
            ->with($userCode, [0, 2])
            ->andReturn([]);

        $controller = new UserInfoController($userService, Mockery::mock(CreditFlowService::class));
        $request = Request::create('/api/user/123456789/trade_accounts', 'GET', [
            'account_types' => '2, 0,2',
        ]);

        $response = $controller->tradeAccounts($request, $userCode);

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_trade_accounts_defaults_to_all_account_types(): void
    {
        $userCode = 123456789;
        $userService = Mockery::mock(UserService::class);
        $userService->shouldReceive('getByUserCode')
            ->once()
            ->with($userCode, ['user_code'])
            ->andReturn(['user_code' => $userCode]);
        $userService->shouldReceive('getUserTradeAccounts')
            ->once()
            ->with($userCode, [])
            ->andReturn([]);

        $controller = new UserInfoController($userService, Mockery::mock(CreditFlowService::class));
        $request = Request::create('/api/user/123456789/trade_accounts', 'GET');

        $response = $controller->tradeAccounts($request, $userCode);

        self::assertSame(200, $response->getStatusCode());
    }

    public function test_credit_flows_route_is_registered(): void
    {
        $route = app('router')->getRoutes()->match(
            Request::create('/api/user/123456789/credit_flows', 'GET')
        );

        self::assertSame('api/user/{user_code}/credit_flows', $route->uri());
        self::assertSame(UserInfoController::class.'@creditFlows', $route->getActionName());
    }

    public function test_credit_flow_options_route_is_registered(): void
    {
        $route = app('router')->getRoutes()->match(
            Request::create('/api/user/123456789/credit_flows/options', 'GET')
        );

        self::assertSame('api/user/{user_code}/credit_flows/options', $route->uri());
        self::assertSame(UserInfoController::class.'@creditFlowOptions', $route->getActionName());
    }

    public function test_credit_flows_normalizes_pagination_and_delegates_to_service(): void
    {
        $userCode = 123456789;
        $userService = Mockery::mock(UserService::class);
        $userService->shouldReceive('getByUserCode')
            ->once()
            ->with($userCode, ['user_code'])
            ->andReturn(['user_code' => $userCode]);
        $creditFlowService = Mockery::mock(CreditFlowService::class);
        $creditFlowService->shouldReceive('getList')
            ->once()
            ->with($userCode, [
                'trade_ticket' => 'T-100',
                'created_start' => '2026-09-01 00:00:00',
                'created_end' => '2026-09-02 00:00:00',
                'fund_type' => '17',
                'credit_type' => '1',
            ], 1, 0)
            ->andReturn(['data' => [], 'total_count' => 0]);

        $controller = new UserInfoController($userService, $creditFlowService);
        $request = CreditFlowListRequest::create("/api/user/{$userCode}/credit_flows", 'GET', [
            'limit' => 0,
            'offset' => -1,
            'trade_ticket' => ' T-100 ',
            'created_start' => '2026-09-01 00:00:00',
            'created_end' => '2026-09-02 00:00:00',
            'fund_type' => '17',
            'credit_type' => '1',
        ]);

        $response = $controller->creditFlows($request, $userCode);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $response->getData(true)['data']['data']);
    }

    public function test_credit_flow_options_delegates_to_service(): void
    {
        $userCode = 123456789;
        $userService = Mockery::mock(UserService::class);
        $userService->shouldReceive('getByUserCode')
            ->once()
            ->with($userCode, ['user_code'])
            ->andReturn(['user_code' => $userCode]);
        $creditFlowService = Mockery::mock(CreditFlowService::class);
        $creditFlowService->shouldReceive('options')
            ->once()
            ->with($userCode)
            ->andReturn([
                'fund_type_options' => [],
                'summary' => [],
            ]);

        $response = (new UserInfoController($userService, $creditFlowService))
            ->creditFlowOptions($userCode);

        self::assertTrue($response->getData(true)['is_succ']);
    }

    public function test_credit_flows_fails_for_a_missing_user(): void
    {
        $userCode = 123456789;
        $userService = Mockery::mock(UserService::class);
        $userService->shouldReceive('getByUserCode')
            ->once()
            ->with($userCode, ['user_code'])
            ->andReturnNull();
        $creditFlowService = Mockery::mock(CreditFlowService::class);
        $creditFlowService->shouldNotReceive('getList');

        $controller = new UserInfoController($userService, $creditFlowService);
        $response = $controller->creditFlows(
            CreditFlowListRequest::create('/api/user/'.$userCode.'/credit_flows', 'GET'),
            $userCode
        );

        self::assertFalse($response->getData(true)['is_succ']);
    }
}
