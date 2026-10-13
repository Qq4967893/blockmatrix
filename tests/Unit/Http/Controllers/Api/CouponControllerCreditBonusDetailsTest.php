<?php

namespace Tests\Unit\Http\Controllers\Api;

use App\Http\Controllers\Api\CouponController;
use App\Services\Api\CouponService;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class CouponControllerCreditBonusDetailsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_credit_bonus_details_route_is_registered(): void
    {
        $route = app('router')->getRoutes()->match(
            Request::create('/api/user_coupons/17/credit_bonus_details', 'GET')
        );

        self::assertSame('api/user_coupons/{user_coupon}/credit_bonus_details', $route->uri());
        self::assertSame(CouponController::class.'@creditBonusDetails', $route->getActionName());
    }

    public function test_credit_bonus_details_passes_normalized_pagination_to_service(): void
    {
        $service = Mockery::mock(CouponService::class);
        $service->shouldReceive('getCreditBonusDetailList')
            ->once()
            ->with(17, 1, 0)
            ->andReturn([
                'data' => [],
                'total_count' => 0,
            ]);

        $request = Request::create('/api/user_coupons/17/credit_bonus_details', 'GET', [
            'limit' => 0,
            'offset' => -1,
        ]);
        $response = (new CouponController($service))->creditBonusDetails($request, 17);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $response->getData(true)['data']['data']);
    }
}
