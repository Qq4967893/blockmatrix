<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\PointRule;
use App\Repositories\Api\IbLevelRepository;
use App\Repositories\Api\PointRuleRepository;
use App\Services\Api\PointRuleService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class PointRuleServicePointsTypeTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_rule_options_include_points_types(): void
    {
        app()->setLocale('zh_CN');

        $service = $this->makeService();
        $options = $service->getRuleOptions();

        $this->assertSame([
            ['code' => PointRule::POINTS_TYPE_XP, 'label' => 'XP'],
            ['code' => PointRule::POINTS_TYPE_DROPLET, 'label' => 'Drop'],
        ], $options['points_types']);
    }

    public function test_create_defaults_points_type_to_xp(): void
    {
        app()->setLocale('zh_CN');

        $repo = Mockery::mock(PointRuleRepository::class);
        $repo->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $payload): array {
                $this->assertSame(PointRule::POINTS_TYPE_XP, $payload['points_type']);

                return $this->makeRuleData($payload);
            });

        $result = $this->makeService($repo)->create($this->makePayload());

        $this->assertSame(PointRule::POINTS_TYPE_XP, $result['points_type']);
        $this->assertSame('XP', $result['points_type_text']);
    }

    public function test_update_preserves_existing_points_type_when_omitted(): void
    {
        app()->setLocale('zh_CN');

        $existingRule = new PointRule;
        $existingRule->id = 10;
        $existingRule->points_type = PointRule::POINTS_TYPE_DROPLET;
        $existingRule->status = PointRule::STATUS_ENABLED;

        $repo = Mockery::mock(PointRuleRepository::class);
        $repo->shouldReceive('findById')
            ->once()
            ->with(10)
            ->andReturn($existingRule);
        $repo->shouldReceive('updateById')
            ->once()
            ->andReturnUsing(function (int $id, array $payload): array {
                $this->assertSame(10, $id);
                $this->assertSame(PointRule::POINTS_TYPE_DROPLET, $payload['points_type']);

                return $this->makeRuleData($payload, 10);
            });

        $result = $this->makeService($repo)->update(10, $this->makePayload());

        $this->assertSame(PointRule::POINTS_TYPE_DROPLET, $result['points_type']);
        $this->assertSame('Drop', $result['points_type_text']);
    }

    private function makeService(?PointRuleRepository $repo = null): PointRuleService
    {
        return new PointRuleService(
            $repo ?? Mockery::mock(PointRuleRepository::class),
            Mockery::mock(IbLevelRepository::class)
        );
    }

    private function makePayload(): array
    {
        return [
            'name_internal' => 'Daily login',
            'locale_configs' => [
                ['lang' => 'en_US', 'name' => 'Daily login'],
            ],
            'event_key' => 'registered',
            'event_params' => [],
            'cycle_limit' => 1,
            'cycle_period' => 0,
            'points' => 10,
            'task_type' => PointRule::TASK_TYPE_NEW_USER,
        ];
    }

    private function makeRuleData(array $payload, int $id = 1): array
    {
        return [
            'id' => $id,
            'name_internal' => $payload['name_internal'],
            'name_external' => $payload['name_external'] ?? [],
            'description' => $payload['description'] ?? [],
            'pop_up' => $payload['pop_up'] ?? null,
            'link' => $payload['link'] ?? null,
            'event_key' => $payload['event_key'],
            'event_params' => $payload['event_params'],
            'cycle_limit' => $payload['cycle_limit'],
            'cycle_period' => $payload['cycle_period'],
            'points' => $payload['points'],
            'points_type' => $payload['points_type'],
            'status' => $payload['status'],
            'task_type' => $payload['task_type'],
            'created_at' => null,
            'updated_at' => null,
        ];
    }
}
