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
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class GrantRuleServiceManualDeliveryTotalCountTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_manual_delivery_defaults_total_count_to_unique_user_count(): void
    {
        $captured = $this->createRule([
            'name' => 'Manual grant without total count',
            'target_type' => GrantRule::TARGET_BALANCE_FUNDING,
            'target' => 100,
            'grant_method' => GrantRule::GRANT_METHOD_MANUAL,
            'user_codes' => [10001, 10001, 10002, 10003],
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ]);

        $this->assertSame(3, $captured['total_count']);
        $this->assertSame([10001, 10002, 10003], $captured['event_params']['user_codes']);
    }

    public function test_manual_delivery_keeps_explicit_total_count(): void
    {
        $captured = $this->createRule([
            'name' => 'Manual grant with explicit total count',
            'target_type' => GrantRule::TARGET_POINTS_XP,
            'target' => 10,
            'grant_method' => GrantRule::GRANT_METHOD_MANUAL,
            'user_codes' => [10001, 10002],
            'total_count' => 50,
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ]);

        $this->assertSame(50, $captured['total_count']);
    }

    public function test_manual_delivery_without_users_defaults_total_count_to_zero(): void
    {
        $captured = $this->createRule([
            'name' => 'Manual grant without users',
            'target_type' => GrantRule::TARGET_BALANCE_FUNDING,
            'target' => 100,
            'grant_method' => GrantRule::GRANT_METHOD_MANUAL,
            'user_codes' => [],
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ]);

        $this->assertSame(0, $captured['total_count']);
    }

    public function test_auto_delivery_without_total_count_keeps_zero(): void
    {
        $captured = $this->createRule([
            'name' => 'Auto grant without total count',
            'target_type' => GrantRule::TARGET_POINTS_XP,
            'target' => 10,
            'grant_method' => GrantRule::GRANT_METHOD_AUTO,
            'event_key' => 'registered',
            'start_at' => '2026-09-01 00:00:00',
            'end_at' => '2026-09-30 23:59:59',
        ]);

        $this->assertSame(0, $captured['total_count']);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createRule(array $payload): array
    {
        $captured = [];

        $grantRuleRepo = Mockery::mock(GrantRuleRepository::class);
        $grantRuleRepo->shouldReceive('existsByName')->once()->andReturnFalse();
        $grantRuleRepo->shouldReceive('createGrantRule')
            ->once()
            ->andReturnUsing(function (array $normalized) use (&$captured): GrantRule {
                $captured = $normalized;

                return new GrantRule;
            });

        $logRepo = Mockery::mock(UserOperationLogRepository::class);
        $logRepo->shouldReceive('addRecord')->byDefault();

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('getByUserCodes')->andReturnUsing(
            static fn (array $userCodes): array => array_map(
                static fn (int $userCode): array => ['user_code' => $userCode, 'username' => 'User '.$userCode],
                $userCodes
            )
        );

        $service = new GrantRuleService(
            Mockery::mock(CouponRepository::class),
            $grantRuleRepo,
            $userRepo,
            Mockery::mock(UserCouponRepository::class),
            $logRepo,
            Mockery::mock(AdminUserRepository::class),
        );

        $this->assertTrue($service->create($payload));

        return $captured;
    }
}
