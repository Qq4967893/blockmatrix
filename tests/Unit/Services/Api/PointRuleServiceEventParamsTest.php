<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\PointRule;
use App\Repositories\Api\IbLevelRepository;
use App\Repositories\Api\PointRuleRepository;
use App\Services\Api\PointRuleService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class PointRuleServiceEventParamsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_create_backfills_missing_event_params_with_zero(): void
    {
        $repo = Mockery::mock(PointRuleRepository::class);
        $repo->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $payload): array {
                self::assertSameEventParams([
                    'trade_account_type' => 1,
                    'count'              => 0,
                    'position_size'      => 0,
                    'lots'               => 0,
                    'margin_order'       => 0,
                ], $payload['event_params']);

                return $this->makeRuleData($payload);
            });

        $payload                  = $this->makePayload('open_trade');
        $payload['event_params']  = ['trade_account_type' => 1];

        $result = $this->makeService($repo)->create($payload);

        self::assertSameEventParams([
            'trade_account_type' => 1,
            'count'              => 0,
            'position_size'      => 0,
            'lots'               => 0,
            'margin_order'       => 0,
        ], $result['event_params']);
    }

    public function test_create_preserves_passed_params_and_blank_becomes_zero(): void
    {
        $repo = Mockery::mock(PointRuleRepository::class);
        $repo->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $payload): array {
                self::assertSameEventParams([
                    'trade_account_type' => 2,
                    'count'              => 5,
                    'position_size'      => 0,
                    'lots'               => 0,
                    'margin_order'       => 0,
                    'profit'             => 0,
                ], $payload['event_params']);

                return $this->makeRuleData($payload);
            });

        $payload                 = $this->makePayload('close_trade');
        $payload['event_params'] = ['trade_account_type' => 2, 'count' => 5, 'profit' => ''];

        $result = $this->makeService($repo)->create($payload);

        self::assertSameEventParams([
            'trade_account_type' => 2,
            'count'              => 5,
            'position_size'      => 0,
            'lots'               => 0,
            'margin_order'       => 0,
            'profit'             => 0,
        ], $result['event_params']);
    }

    public function test_create_event_without_variables_keeps_event_params_untouched(): void
    {
        $repo = Mockery::mock(PointRuleRepository::class);
        $repo->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (array $payload): array {
                $this->assertSame([], $payload['event_params']);

                return $this->makeRuleData($payload);
            });

        $result = $this->makeService($repo)->create($this->makePayload('registered'));

        $this->assertSame([], $result['event_params']);
    }

    public function test_update_backfills_missing_event_params_with_zero(): void
    {
        $existingRule = new PointRule;
        $existingRule->id = 10;
        $existingRule->event_key = 'open_trade';
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
                self::assertSameEventParams([
                    'trade_account_type' => 1,
                    'count'              => 0,
                    'position_size'      => 0,
                    'lots'               => 0,
                    'margin_order'       => 0,
                ], $payload['event_params']);

                return $this->makeRuleData($payload, 10);
            });

        $payload                  = $this->makePayload('open_trade');
        $payload['event_params']  = ['trade_account_type' => 1];

        $result = $this->makeService($repo)->update(10, $payload);

        self::assertSameEventParams([
            'trade_account_type' => 1,
            'count'              => 0,
            'position_size'      => 0,
            'lots'               => 0,
            'margin_order'       => 0,
        ], $result['event_params']);
    }

    private static function assertSameEventParams(array $expected, array $actual): void
    {
        ksort($expected);
        ksort($actual);
        self::assertSame($expected, $actual);
    }

    private function makeService(?PointRuleRepository $repo = null): PointRuleService
    {
        $ibLevelRepo = Mockery::mock(IbLevelRepository::class);
        $ibLevelRepo->shouldReceive('getEnabledLevelParams')->andReturn([]);

        return new PointRuleService(
            $repo ?? Mockery::mock(PointRuleRepository::class),
            $ibLevelRepo
        );
    }

    private function makePayload(string $eventKey): array
    {
        return [
            'name_internal' => 'Daily login',
            'locale_configs' => [
                ['lang' => 'en_US', 'name' => 'Daily login'],
            ],
            'event_key' => $eventKey,
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
