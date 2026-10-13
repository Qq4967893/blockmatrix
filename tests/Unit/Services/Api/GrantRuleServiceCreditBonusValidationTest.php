<?php

namespace Tests\Unit\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Api\Coupon;
use App\Models\Api\GrantRule;
use App\Models\Api\UserCoupon;
use App\Repositories\Api\CouponRepository;
use App\Repositories\Api\GrantRuleRepository;
use App\Repositories\Api\UserCouponRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Services\Api\GrantRuleService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;
use Scaffold\Framework\Enums\TypeCoupon;
use Scaffold\Framework\Enums\TypeGrantEvent;
use Tests\TestCase;

class GrantRuleServiceCreditBonusValidationTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_manual_grant_credit_bonus_rejects_users_with_existing_bonus(): void
    {
        app()->setLocale('zh_CN');

        $coupon = new Coupon;
        $coupon->id = 100;
        $coupon->coupon_type = TypeCoupon::CreditBonus;

        $couponRepo = Mockery::mock(CouponRepository::class);
        $couponRepo->shouldReceive('findById')->with(100)->andReturn($coupon);

        $userCouponRepo = Mockery::mock(UserCouponRepository::class);
        $userCouponRepo->shouldReceive('findUserCodesWithExistingCreditBonus')
            ->once()
            ->with([10001, 10002])
            ->andReturn([10001]);

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('getByUserCodes')
            ->once()
            ->with([10001, 10002], ['user_code', 'username'])
            ->andReturn([
                ['user_code' => 10001, 'username' => 'Client A'],
                ['user_code' => 10002, 'username' => 'Client B'],
            ]);

        $grantRuleRepo = Mockery::mock(GrantRuleRepository::class);
        $grantRuleRepo->shouldReceive('existsByName')->once()->andReturnFalse();

        $service = $this->makeService(
            couponRepo: $couponRepo,
            grantRuleRepo: $grantRuleRepo,
            userCouponRepo: $userCouponRepo,
            userRepo: $userRepo
        );

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessageMatches('/10001/');

        $service->create([
            'name' => 'Credit bonus manual grant',
            'target_type' => GrantRule::TARGET_COUPON,
            'target' => 100,
            'grant_method' => GrantRule::GRANT_METHOD_MANUAL,
            'user_codes' => [10001, 10002],
            'total_count' => 100,
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ]);
    }

    public function test_manual_grant_non_credit_bonus_allows_any_user(): void
    {
        $coupon = new Coupon;
        $coupon->id = 200;
        $coupon->coupon_type = TypeCoupon::TradeCashback;

        $couponRepo = Mockery::mock(CouponRepository::class);
        $couponRepo->shouldReceive('findById')->with(200)->andReturn($coupon);

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('getByUserCodes')
            ->once()
            ->with([10001, 10002], ['user_code', 'username'])
            ->andReturn([
                ['user_code' => 10001, 'username' => 'Client A'],
                ['user_code' => 10002, 'username' => 'Client B'],
            ]);

        $grantRuleRepo = Mockery::mock(GrantRuleRepository::class);
        $grantRuleRepo->shouldReceive('existsByName')->once()->andReturnFalse();
        $grantRuleRepo->shouldReceive('createGrantRule')->once()->andReturn(new GrantRule);

        $service = $this->makeService(
            couponRepo: $couponRepo,
            grantRuleRepo: $grantRuleRepo,
            userRepo: $userRepo
        );

        $result = $service->create([
            'name' => 'Trade cashback manual grant',
            'target_type' => GrantRule::TARGET_COUPON,
            'target' => 200,
            'grant_method' => GrantRule::GRANT_METHOD_MANUAL,
            'user_codes' => [10001, 10002],
            'total_count' => 100,
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ]);

        $this->assertTrue($result);
    }

    public function test_auto_grant_credit_bonus_sets_skip_flag(): void
    {
        $coupon = new Coupon;
        $coupon->id = 100;
        $coupon->coupon_type = TypeCoupon::CreditBonus;

        $couponRepo = Mockery::mock(CouponRepository::class);
        $couponRepo->shouldReceive('findById')->with(100)->andReturn($coupon);

        $grantRuleRepo = Mockery::mock(GrantRuleRepository::class);
        $grantRuleRepo->shouldReceive('existsByName')->once()->andReturnFalse();

        $createdRule = new GrantRule;
        $createdRule->id = 1;
        $grantRuleRepo->shouldReceive('createGrantRule')
            ->once()
            ->with(Mockery::on(fn (array $data): bool => ($data['event_params']['skip_if_has_bonus'] ?? false) === true))
            ->andReturn($createdRule);

        $service = $this->makeService(
            couponRepo: $couponRepo,
            grantRuleRepo: $grantRuleRepo
        );

        $result = $service->create([
            'name' => 'Credit bonus auto grant',
            'target_type' => GrantRule::TARGET_COUPON,
            'target' => 100,
            'grant_method' => GrantRule::GRANT_METHOD_AUTO,
            'event_key' => TypeGrantEvent::FirstDeposited->value,
            'event_params' => ['amount' => '1000'],
            'total_count' => 100,
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ]);

        $this->assertTrue($result);
    }

    public function test_auto_grant_non_credit_bonus_does_not_set_skip_flag(): void
    {
        $coupon = new Coupon;
        $coupon->id = 200;
        $coupon->coupon_type = TypeCoupon::LossRecover;

        $couponRepo = Mockery::mock(CouponRepository::class);
        $couponRepo->shouldReceive('findById')->with(200)->andReturn($coupon);

        $grantRuleRepo = Mockery::mock(GrantRuleRepository::class);
        $grantRuleRepo->shouldReceive('existsByName')->once()->andReturnFalse();

        $createdRule = new GrantRule;
        $createdRule->id = 2;
        $grantRuleRepo->shouldReceive('createGrantRule')
            ->once()
            ->with(Mockery::on(fn (array $data): bool => ! array_key_exists('skip_if_has_bonus', $data['event_params'] ?? [])))
            ->andReturn($createdRule);

        $service = $this->makeService(
            couponRepo: $couponRepo,
            grantRuleRepo: $grantRuleRepo
        );

        $result = $service->create([
            'name' => 'Loss recover auto grant',
            'target_type' => GrantRule::TARGET_COUPON,
            'target' => 200,
            'grant_method' => GrantRule::GRANT_METHOD_AUTO,
            'event_key' => TypeGrantEvent::FirstDeposited->value,
            'event_params' => ['amount' => '1000'],
            'total_count' => 100,
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ]);

        $this->assertTrue($result);
    }

    public function test_filter_internal_event_params_removes_skip_flag(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('filterInternalEventParams');
        $method->setAccessible(true);

        $result = $method->invoke($service, ['amount' => '1000', 'skip_if_has_bonus' => true]);
        $this->assertSame(['amount' => '1000'], $result);

        $result2 = $method->invoke($service, ['amount' => '1000']);
        $this->assertSame(['amount' => '1000'], $result2);
    }

    public function test_manual_grant_credit_bonus_passes_when_no_existing_bonus(): void
    {
        $coupon = new Coupon;
        $coupon->id = 100;
        $coupon->coupon_type = TypeCoupon::CreditBonus;

        $couponRepo = Mockery::mock(CouponRepository::class);
        $couponRepo->shouldReceive('findById')->with(100)->andReturn($coupon);

        $userCouponRepo = Mockery::mock(UserCouponRepository::class);
        $userCouponRepo->shouldReceive('findUserCodesWithExistingCreditBonus')
            ->once()
            ->with([10003])
            ->andReturn([]);

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('getByUserCodes')
            ->once()
            ->with([10003], ['user_code', 'username'])
            ->andReturn([
                ['user_code' => 10003, 'username' => 'Client C'],
            ]);

        $grantRuleRepo = Mockery::mock(GrantRuleRepository::class);
        $grantRuleRepo->shouldReceive('existsByName')->once()->andReturnFalse();
        $grantRuleRepo->shouldReceive('createGrantRule')->once()->andReturn(new GrantRule);

        $service = $this->makeService(
            couponRepo: $couponRepo,
            grantRuleRepo: $grantRuleRepo,
            userCouponRepo: $userCouponRepo,
            userRepo: $userRepo
        );

        $result = $service->create([
            'name' => 'Credit bonus manual grant - new user',
            'target_type' => GrantRule::TARGET_COUPON,
            'target' => 100,
            'grant_method' => GrantRule::GRANT_METHOD_MANUAL,
            'user_codes' => [10003],
            'total_count' => 100,
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ]);

        $this->assertTrue($result);
    }

    public function test_manual_grant_restricted_credit_bonus_rejects_users_with_existing_bonus(): void
    {
        app()->setLocale('zh_CN');

        $coupon = new Coupon;
        $coupon->id = 300;
        $coupon->coupon_type = TypeCoupon::RestrictedCreditBonus;

        $couponRepo = Mockery::mock(CouponRepository::class);
        $couponRepo->shouldReceive('findById')->with(300)->andReturn($coupon);

        $userCouponRepo = Mockery::mock(UserCouponRepository::class);
        $userCouponRepo->shouldReceive('findUserCodesWithExistingCreditBonus')
            ->once()
            ->with([10001])
            ->andReturn([10001]);

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('getByUserCodes')
            ->once()
            ->with([10001], ['user_code', 'username'])
            ->andReturn([
                ['user_code' => 10001, 'username' => 'Client A'],
            ]);

        $grantRuleRepo = Mockery::mock(GrantRuleRepository::class);
        $grantRuleRepo->shouldReceive('existsByName')->once()->andReturnFalse();

        $service = $this->makeService(
            couponRepo: $couponRepo,
            grantRuleRepo: $grantRuleRepo,
            userCouponRepo: $userCouponRepo,
            userRepo: $userRepo
        );

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessageMatches('/10001/');

        $service->create([
            'name' => 'Restricted credit bonus manual grant',
            'target_type' => GrantRule::TARGET_COUPON,
            'target' => 300,
            'grant_method' => GrantRule::GRANT_METHOD_MANUAL,
            'user_codes' => [10001],
            'total_count' => 100,
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ]);
    }

    public function test_auto_grant_restricted_credit_bonus_sets_skip_flag(): void
    {
        $coupon = new Coupon;
        $coupon->id = 300;
        $coupon->coupon_type = TypeCoupon::RestrictedCreditBonus;

        $couponRepo = Mockery::mock(CouponRepository::class);
        $couponRepo->shouldReceive('findById')->with(300)->andReturn($coupon);

        $grantRuleRepo = Mockery::mock(GrantRuleRepository::class);
        $grantRuleRepo->shouldReceive('existsByName')->once()->andReturnFalse();

        $createdRule = new GrantRule;
        $createdRule->id = 3;
        $grantRuleRepo->shouldReceive('createGrantRule')
            ->once()
            ->with(Mockery::on(fn (array $data): bool => ($data['event_params']['skip_if_has_bonus'] ?? false) === true))
            ->andReturn($createdRule);

        $service = $this->makeService(
            couponRepo: $couponRepo,
            grantRuleRepo: $grantRuleRepo
        );

        $result = $service->create([
            'name' => 'Restricted credit bonus auto grant',
            'target_type' => GrantRule::TARGET_COUPON,
            'target' => 300,
            'grant_method' => GrantRule::GRANT_METHOD_AUTO,
            'event_key' => TypeGrantEvent::FirstDeposited->value,
            'event_params' => ['amount' => '1000'],
            'total_count' => 100,
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ]);

        $this->assertTrue($result);
    }

    private function makeService(
        ?CouponRepository $couponRepo = null,
        ?GrantRuleRepository $grantRuleRepo = null,
        ?UserRepository $userRepo = null,
        ?UserCouponRepository $userCouponRepo = null
    ): GrantRuleService {
        $logRepo = Mockery::mock(UserOperationLogRepository::class);
        $logRepo->shouldReceive('addRecord')->byDefault();

        return new GrantRuleService(
            $couponRepo ?? Mockery::mock(CouponRepository::class),
            $grantRuleRepo ?? Mockery::mock(GrantRuleRepository::class),
            $userRepo ?? Mockery::mock(UserRepository::class),
            $userCouponRepo ?? Mockery::mock(UserCouponRepository::class),
            $logRepo,
            Mockery::mock(AdminUserRepository::class),
        );
    }
}
