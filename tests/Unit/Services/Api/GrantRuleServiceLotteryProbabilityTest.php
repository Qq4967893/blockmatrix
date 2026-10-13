<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\Coupon;
use App\Models\Api\GrantRule;
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

class GrantRuleServiceLotteryProbabilityTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_create_and_update_store_lottery_probability_as_basis_points(): void
    {
        $coupon = new Coupon;
        $coupon->id = 100;
        $coupon->coupon_type = TypeCoupon::LossRecover;

        $couponRepo = Mockery::mock(CouponRepository::class);
        $couponRepo->shouldReceive('findById')->with(100)->andReturn($coupon);

        $grantRuleRepo = Mockery::mock(GrantRuleRepository::class);
        $grantRuleRepo->shouldReceive('existsByName')->twice()->andReturnFalse();

        $createdRule = $this->makeGrantRule();
        $updatedRule = $this->makeGrantRule();
        $storedCreateParams = [];
        $storedUpdateParams = [];

        $grantRuleRepo->shouldReceive('createGrantRule')
            ->once()
            ->with(Mockery::on(function (array $data) use (&$storedCreateParams): bool {
                $storedCreateParams = $data['event_params'];

                return true;
            }))
            ->andReturn($createdRule);

        $existingRule = $this->makeGrantRule();
        $existingRule->id = 10;
        $grantRuleRepo->shouldReceive('findById')->once()->with(10)->andReturn($existingRule);
        $grantRuleRepo->shouldReceive('updateById')
            ->once()
            ->with(10, Mockery::on(function (array $data) use (&$storedUpdateParams): bool {
                $storedUpdateParams = $data['event_params'];

                return true;
            }), $existingRule)
            ->andReturn($updatedRule);

        $operationLogRepo = Mockery::mock(UserOperationLogRepository::class);
        $operationLogRepo->shouldReceive('addRecord')->twice();

        $service = $this->makeService(
            couponRepo: $couponRepo,
            grantRuleRepo: $grantRuleRepo,
            operationLogRepo: $operationLogRepo
        );
        $payload = $this->lotteryPayload();

        $this->assertTrue($service->create($payload));
        $this->assertTrue($service->update(10, $payload));

        $this->assertSame(3000, $storedCreateParams['weight']);
        $this->assertSame(3000, $storedUpdateParams['weight']);
    }

    public function test_list_returns_lottery_probability_as_percentage(): void
    {
        app()->setLocale('zh_CN');

        $couponRepo = Mockery::mock(CouponRepository::class);
        $result = $this->invokeGrantRuleServiceMethod(
            $couponRepo,
            'formatGrantRule',
            [
                'id' => 1,
                'name' => 'Lottery grant rule',
                'target_type' => GrantRule::TARGET_COUPON,
                'target' => 100,
                'event_key' => TypeGrantEvent::Lottery->value,
                'event_params' => json_encode([
                    'weight' => 3000,
                    'relation' => 'and',
                ], JSON_THROW_ON_ERROR),
                'total_count' => 100,
                'granted_count' => 10,
                'post_count' => 10,
                'start_at' => '2026-09-01 00:00:00',
                'end_at' => '2026-09-30 23:59:59',
            ],
            [],
            []
        );

        $this->assertSame(30, $result['event_params']['weight']);
        $this->assertContains('概率：30', $result['event_params_text']);
    }

    /**
     * @return array<string, mixed>
     */
    private function lotteryPayload(): array
    {
        return [
            'name' => 'Lottery grant rule',
            'target_type' => GrantRule::TARGET_COUPON,
            'target' => 100,
            'grant_method' => GrantRule::GRANT_METHOD_AUTO,
            'event_key' => TypeGrantEvent::Lottery->value,
            'event_params' => [
                'identifier' => 1,
                'weight' => 30,
                'relation' => 'and',
            ],
            'total_count' => 100,
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ];
    }

    private function makeGrantRule(): GrantRule
    {
        $grantRule = new GrantRule;
        $grantRule->id = 1;
        $grantRule->name = 'Lottery grant rule';
        $grantRule->target_type = GrantRule::TARGET_COUPON;
        $grantRule->target = 100;
        $grantRule->event_key = TypeGrantEvent::Lottery->value;
        $grantRule->event_params = [
            'identifier' => 1,
            'weight' => 3000,
            'relation' => 'and',
        ];
        $grantRule->total_count = 100;
        $grantRule->status = GrantRule::STATUS_DISABLED;
        $grantRule->start_at = '2026-09-01 00:00:00';
        $grantRule->end_at = '2026-09-30 23:59:59';

        return $grantRule;
    }

    private function makeService(
        ?CouponRepository $couponRepo = null,
        ?GrantRuleRepository $grantRuleRepo = null,
        ?UserOperationLogRepository $operationLogRepo = null
    ): GrantRuleService {
        return new GrantRuleService(
            $couponRepo ?? Mockery::mock(CouponRepository::class),
            $grantRuleRepo ?? Mockery::mock(GrantRuleRepository::class),
            Mockery::mock(UserRepository::class),
            Mockery::mock(UserCouponRepository::class),
            $operationLogRepo ?? Mockery::mock(UserOperationLogRepository::class),
            Mockery::mock(AdminUserRepository::class),
        );
    }

    private function invokeGrantRuleServiceMethod(
        CouponRepository $couponRepo,
        string $methodName,
        mixed ...$args
    ): mixed {
        $reflection = new ReflectionClass(GrantRuleService::class);
        $service = $reflection->newInstanceWithoutConstructor();

        $couponRepoProperty = $reflection->getProperty('couponRepo');
        $couponRepoProperty->setAccessible(true);
        $couponRepoProperty->setValue($service, $couponRepo);

        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($service, ...$args);
    }
}
