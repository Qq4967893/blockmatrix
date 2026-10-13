<?php

namespace Tests\Unit\Services\Api;

use App\Repositories\Api\IbAccountRepository;
use App\Repositories\Api\IbCommissionRuleRepository;
use App\Repositories\Api\IbLevelRepository;
use App\Services\Api\IbLevelOrderService;
use App\Services\Api\IbLevelService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;
use Tests\TestCase;

class IbLevelServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_get_list_returns_is_initial_fields(): void
    {
        $service = $this->makeService();

        $levelRepo = $this->getProperty($service, 'levelRepo');
        $levelRepo->shouldReceive('getList')
            ->once()
            ->with([], 20, 0)
            ->andReturn([[
                [
                    'id' => 1,
                    'level_pos' => 1,
                    'name' => ['en_US' => 'Gold'],
                    'commission_rule_id' => 11,
                    'commission_rule_name' => ['en_US' => 'Rule A'],
                    'commission_rule_base_commission_rate' => '2.5000',
                    'is_initial' => 1,
                    'active_trader_threshold' => 1,
                    'team_position_size_threshold' => '1.000000',
                    'created_at' => '2026-08-12 10:00:00',
                    'updated_at' => '2026-08-12 10:00:00',
                ],
            ], 1]);
        $levelRepo->shouldReceive('getMaxActiveLevelPos')->once()->andReturn(1);

        $result = $service->getList([], 20, 0);

        $this->assertSame(1, $result['data'][0]['is_initial']);
        $this->assertSame(trans('user.boolean.yes'), $result['data'][0]['is_initial_text']);
        $this->assertSame('2.5', $result['data'][0]['commission_rule_base_commission_rate']);
    }

    public function test_resolve_update_is_initial_keeps_existing_value_when_missing(): void
    {
        $service = $this->makeService();

        $method = $this->getMethod($service, 'resolveUpdateIsInitial');
        $this->assertSame(1, $method->invoke($service, [], 1, 0));
        $this->assertSame(0, $method->invoke($service, ['is_initial' => null], 0, 1));
        $this->assertSame(1, $method->invoke($service, ['is_initial' => 1], 0, 1));
    }

    private function makeService(): IbLevelService
    {
        $levelRepo = Mockery::mock(IbLevelRepository::class);
        $commissionRuleRepo = Mockery::mock(IbCommissionRuleRepository::class);
        $ibAccountRepo = Mockery::mock(IbAccountRepository::class);
        $levelOrderService = Mockery::mock(IbLevelOrderService::class);
        $commissionRuleRepo->shouldReceive('getEffectiveOptions')->andReturn([]);

        return new IbLevelService($levelRepo, $commissionRuleRepo, $ibAccountRepo, $levelOrderService);
    }

    private function getProperty(object $object, string $property): mixed
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($object);
    }

    private function getMethod(object $object, string $method): \ReflectionMethod
    {
        $reflection = new ReflectionClass($object);
        $methodRef = $reflection->getMethod($method);
        $methodRef->setAccessible(true);

        return $methodRef;
    }
}
