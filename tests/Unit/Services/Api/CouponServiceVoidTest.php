<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\Coupon;
use App\Repositories\Api\CouponRepository;
use App\Repositories\Api\GrantRuleRepository;
use App\Repositories\Api\SourceRepository;
use App\Repositories\Api\UserCouponRepository;
use App\Repositories\Api\UserCreditLedgerRepository;
use App\Repositories\Api\UserCreditRecordRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Repositories\Et6\TradeRepository;
use App\Services\Api\CouponService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Scaffold\TradeSystem\Facades\TsCoupon;
use Tests\TestCase;

class CouponServiceVoidTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_void_coupon_calls_trade_system_before_updating_user_coupons(): void
    {
        $coupon = new Coupon([
            'id' => 10,
            'status' => Coupon::STATUS_ENABLED,
            'name_internal' => 'Coupon A',
        ]);
        $updatedCoupon = new Coupon([
            'id' => 10,
            'status' => Coupon::STATUS_VOIDED,
            'name_internal' => 'Coupon A',
        ]);
        $calls = [];

        $connection = Mockery::mock();
        $connection->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static function (callable $callback): mixed {
                return $callback();
            });
        DB::shouldReceive('connection')->with(DB_API)->once()->andReturn($connection);

        TsCoupon::shouldReceive('voidCoupon')
            ->once()
            ->with(10, [])
            ->andReturnUsing(static function () use (&$calls): object {
                $calls[] = 'trade-system';

                return (object) ['is_succ' => true];
            });

        $couponRepo = Mockery::mock(CouponRepository::class);
        $grantRuleRepo = Mockery::mock(GrantRuleRepository::class);
        $userCouponRepo = Mockery::mock(UserCouponRepository::class);
        $tradeRepo = Mockery::mock(TradeRepository::class);
        $sourceRepo = Mockery::mock(SourceRepository::class);
        $logRepo = Mockery::mock(UserOperationLogRepository::class);

        $couponRepo->shouldReceive('findById')->once()->with(10)->andReturn($coupon);
        $grantRuleRepo->shouldReceive('getEnabledUnfinishedNamesByCouponId')
            ->once()
            ->with(10)
            ->andReturn([]);
        $couponRepo->shouldReceive('updateById')
            ->once()
            ->with(10, ['status' => Coupon::STATUS_VOIDED], $coupon)
            ->andReturnUsing(static function () use ($updatedCoupon, &$calls): Coupon {
                $calls[] = 'coupon-update';

                return $updatedCoupon;
            });
        $userCouponRepo->shouldReceive('forceVoidByCouponId')
            ->once()
            ->with(10)
            ->andReturnUsing(static function () use (&$calls): int {
                $calls[] = 'user-coupon-update';

                return 3;
            });
        $logRepo->shouldReceive('addRecord')->once();

        $service = new CouponService(
            $couponRepo,
            $grantRuleRepo,
            $userCouponRepo,
            $tradeRepo,
            $sourceRepo,
            $logRepo,
            Mockery::mock(UserCreditRecordRepository::class),
            Mockery::mock(UserCreditLedgerRepository::class),
        );

        $result = $service->updateStatus(10, Coupon::STATUS_VOIDED);

        $this->assertSame([
            'id' => 10,
            'status' => Coupon::STATUS_VOIDED,
            'status_text' => $service->couponStatusLabel(Coupon::STATUS_VOIDED),
        ], $result);
        $this->assertSame(['coupon-update', 'trade-system', 'user-coupon-update'], $calls);
    }
}
