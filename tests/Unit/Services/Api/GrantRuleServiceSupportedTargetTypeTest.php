<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\GrantRule;
use App\Repositories\Api\CouponRepository;
use App\Repositories\Api\GrantRuleRepository;
use App\Repositories\Api\UserCouponRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Services\Api\GrantRuleService;
use Carbon\Carbon;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;
use Scaffold\Framework\Enums\TypeGrantEvent;
use Tests\TestCase;

class GrantRuleServiceSupportedTargetTypeTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_create_and_update_support_non_coupon_targets(): void
    {
        $grantRuleRepo = Mockery::mock(GrantRuleRepository::class);
        $grantRuleRepo->shouldReceive('existsByName')->twice()->andReturnFalse();

        $createdRule = $this->makeGrantRule();
        $updatedRule = $this->makeGrantRule();
        $storedCreate = [];
        $storedUpdate = [];

        $grantRuleRepo->shouldReceive('createGrantRule')
            ->once()
            ->with(Mockery::on(function (array $data) use (&$storedCreate): bool {
                $storedCreate = $data;

                return true;
            }))
            ->andReturn($createdRule);

        $existingRule = $this->makeGrantRule();
        $existingRule->id = 10;
        $grantRuleRepo->shouldReceive('findById')->once()->with(10)->andReturn($existingRule);
        $grantRuleRepo->shouldReceive('updateById')
            ->once()
            ->with(10, Mockery::on(function (array $data) use (&$storedUpdate): bool {
                $storedUpdate = $data;

                return true;
            }), $existingRule)
            ->andReturn($updatedRule);

        $operationLogRepo = Mockery::mock(UserOperationLogRepository::class);
        $operationLogRepo->shouldReceive('addRecord')->twice();

        $service = $this->makeService(
            grantRuleRepo: $grantRuleRepo,
            operationLogRepo: $operationLogRepo
        );
        $payload = $this->payload();

        $this->assertTrue($service->create($payload));
        $this->assertTrue($service->update(10, $payload));

        foreach ([$storedCreate, $storedUpdate] as $stored) {
            $this->assertSame(GrantRule::TARGET_BALANCE_FUNDING, $stored['target_type']);
            $this->assertSame(500, $stored['target']);
        }
    }

    public function test_non_coupon_target_can_be_enabled_without_coupon_lookup(): void
    {
        $couponRepo = Mockery::mock(CouponRepository::class);
        $service = $this->makeService(couponRepo: $couponRepo);
        $grantRule = $this->makeGrantRule();
        $grantRule->total_count = 10;
        $grantRule->granted_count = 8;

        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('ensureGrantRuleCanBeEnabled');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($service, $grantRule));
    }

    public function test_non_coupon_target_name_uses_target_type_label(): void
    {
        $couponRepo = Mockery::mock(CouponRepository::class);
        $service = $this->makeService(couponRepo: $couponRepo);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('buildGrantTargetName');
        $method->setAccessible(true);

        $targetNames = [];
        $expectedNames = [];
        foreach (GrantRule::SUPPORTED_TARGET_TYPES as $targetType) {
            if ($targetType === GrantRule::TARGET_COUPON) {
                continue;
            }

            $targetNames[$targetType] = $method->invoke($service, [
                'target_type' => $targetType,
                'target' => 500,
            ]);
            $expectedNames[$targetType] = trans('grant_rule.target_type.' . $targetType);
        }

        $this->assertSame($expectedNames, $targetNames);
        $this->assertNotContains('500', $targetNames);
    }

    public function test_non_coupon_post_count_returns_target_amount(): void
    {
        $service = $this->makeService();
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('formatGrantRule');
        $method->setAccessible(true);

        $format = fn (int $targetType, int $target, int $postCount): array => $method->invoke($service, [
            'id' => 1,
            'name' => 'Rule',
            'target_type' => $targetType,
            'target' => $target,
            'event_key' => null,
            'event_params' => null,
            'status' => GrantRule::STATUS_ENABLED,
            'total_count' => 10,
            'post_count' => $postCount,
            'start_at' => null,
            'end_at' => null,
        ], [], []);

        $couponRow = $format(GrantRule::TARGET_COUPON, 1001, 8);
        $xpRow = $format(GrantRule::TARGET_POINTS_XP, 500, 0);

        $this->assertSame(8, $couponRow['post_count']);
        $this->assertSame(500, $xpRow['post_count']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Funding grant rule',
            'target_type' => GrantRule::TARGET_BALANCE_FUNDING,
            'target' => 500,
            'grant_method' => GrantRule::GRANT_METHOD_AUTO,
            'event_key' => TypeGrantEvent::FirstDeposited->value,
            'event_params' => ['amount' => '1000'],
            'total_count' => 100,
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ];
    }

    private function makeGrantRule(): GrantRule
    {
        $grantRule = new GrantRule;
        $grantRule->id = 1;
        $grantRule->name = 'Funding grant rule';
        $grantRule->target_type = GrantRule::TARGET_BALANCE_FUNDING;
        $grantRule->target = 500;
        $grantRule->event_key = TypeGrantEvent::FirstDeposited->value;
        $grantRule->event_params = ['amount' => '1000'];
        $grantRule->total_count = 100;
        $grantRule->granted_count = 0;
        $grantRule->status = GrantRule::STATUS_DISABLED;
        $grantRule->start_at = Carbon::now()->subDay()->format('Y-m-d H:i:s');
        $grantRule->end_at = Carbon::now()->addMonth()->format('Y-m-d H:i:s');

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
}
