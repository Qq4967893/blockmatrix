<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\Badge;
use App\Repositories\Api\BadgeRepository;
use App\Repositories\Api\CopyTradingRepository;
use App\Services\Api\BadgeService;
use Mockery;
use Tests\TestCase;

class BadgeServiceCrudEventParamsTest extends TestCase
{
    public function test_create_stores_relation_with_normalized_event_params_and_returns_badge(): void
    {
        $repo = Mockery::mock(BadgeRepository::class);
        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $service = new BadgeService($repo, $copyTradingRepo);

        $repo->shouldReceive('existsByName')->once()->with(['Close trader'], null)->andReturnFalse();
        $repo->shouldReceive('existsByShortName')->once()->with('closer', null)->andReturnFalse();
        $repo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data): bool {
                $this->assertSame([
                    'trade_account_type' => 1,
                    'lots' => 2.5,
                    'relation' => 'and',
                ], $data['event_params']);

                return true;
            }))
            ->andReturn($this->makeBadge([
                'id' => 10,
                'short_name' => 'closer',
                'event_key' => 'close_trade',
                'event_params' => [
                    'trade_account_type' => 1,
                    'lots' => 2.5,
                    'relation' => 'and',
                ],
                'name' => ['en_US' => 'Close trader'],
                'description' => [],
            ]));

        $badge = $service->create([
            'icon' => '',
            'short_name' => 'closer',
            'event_key' => 'close_trade',
            'event_params' => [
                ['param_key' => 'trade_account_type', 'param_value' => '1'],
                ['param_key' => 'lots', 'param_value' => '2.5'],
            ],
            'relation' => 'and',
            'locale_configs' => [
                ['lang' => 'en_US', 'name' => 'Close trader'],
            ],
        ]);

        $this->assertSame([
            'trade_account_type' => 1,
            'lots' => 2.5,
        ], $badge['event_params']);
        $this->assertCount(2, $badge['event_params_text']);
        $this->assertSame(
            sprintf(
                '%s 、 %s',
                $this->eventParamText('lots', 2.5),
                $this->eventParamText('trade_account_type', 1)
            ),
            $badge['event_text']
        );
        $this->assertSame('and', $badge['relation']);
    }

    public function test_update_replaces_relation_and_returns_normalized_badge(): void
    {
        $repo = Mockery::mock(BadgeRepository::class);
        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $service = new BadgeService($repo, $copyTradingRepo);
        $existing = $this->makeBadge([
            'id' => 10,
            'short_name' => 'closer',
            'event_key' => 'close_trade',
            'event_params' => [
                'trade_account_type' => 1,
                'lots' => 1,
                'relation' => 'or',
            ],
            'name' => ['en_US' => 'Close trader'],
            'description' => [],
        ]);
        $updated = $this->makeBadge([
            'id' => 10,
            'short_name' => 'closer',
            'event_key' => 'close_trade',
            'event_params' => [
                'trade_account_type' => 1,
                'lots' => 3,
                'relation' => 'and',
            ],
            'name' => ['en_US' => 'Close trader'],
            'description' => [],
        ]);

        $repo->shouldReceive('findById')->once()->with(10)->andReturn($existing);
        $repo->shouldReceive('existsByShortName')->once()->with('closer', 10)->andReturnFalse();
        $repo->shouldReceive('existsByName')->once()->with(['Close trader'], 10)->andReturnFalse();
        $repo->shouldReceive('updateById')
            ->once()
            ->with(
                10,
                Mockery::on(function (array $data): bool {
                    $this->assertArrayNotHasKey('relation', $data);
                    $this->assertSame([
                        'trade_account_type' => 1,
                        'lots' => 3,
                        'relation' => 'and',
                    ], $data['event_params']);

                    return true;
                }),
                $existing
            )
            ->andReturn($updated);

        $badge = $service->update(10, [
            'short_name' => 'closer',
            'event_key' => 'close_trade',
            'event_params' => [
                'trade_account_type' => 1,
                'lots' => 3,
            ],
            'relation' => 'and',
            'locale_configs' => [
                ['lang' => 'en_US', 'name' => 'Close trader'],
            ],
        ]);

        $this->assertIsArray($badge);
        $this->assertSame([
            'trade_account_type' => 1,
            'lots' => 3,
        ], $badge['event_params']);
        $this->assertCount(2, $badge['event_params_text']);
        $this->assertSame(
            sprintf(
                '%s 、 %s',
                $this->eventParamText('lots', 3),
                $this->eventParamText('trade_account_type', 1)
            ),
            $badge['event_text']
        );
        $this->assertSame('and', $badge['relation']);
    }

    public function test_list_returns_event_params_object_and_relation_separately(): void
    {
        $repo = Mockery::mock(BadgeRepository::class);
        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $service = new BadgeService($repo, $copyTradingRepo);

        $repo->shouldReceive('getList')
            ->once()
            ->with('', null, 20, 0)
            ->andReturn([[
                [
                    'id' => 10,
                    'icon' => '',
                    'short_name' => 'closer',
                    'event_key' => 'close_trade',
                    'event_params' => [
                        'relation' => 'or',
                        'lots' => 10,
                        'margin_order' => 1000,
                        'trade_account_type' => 0,
                    ],
                    'status' => Badge::STATUS_ENABLED,
                    'name' => ['en_US' => 'Close trader'],
                    'description' => [],
                ],
            ], 1]);

        $result = $service->getList('', null, 20, 0);

        $this->assertSame(1, $result['total_count']);
        $this->assertSame([
            'lots' => 10,
            'margin_order' => 1000,
            'trade_account_type' => 0,
        ], $result['data'][0]['event_params']);
        $this->assertSame('or', $result['data'][0]['relation']);
        $this->assertSame(
            trans('scaffold::type_badge_event.close_trade.description'),
            $result['data'][0]['event_key_text']
        );
        $this->assertSame([
            $this->eventParamText('lots', 10),
            $this->eventParamText('margin_order', 1000),
            $this->eventParamText('trade_account_type', 0),
        ], $result['data'][0]['event_params_text']);
        $this->assertSame(
            sprintf(
                '%s or %s 、 %s',
                $this->eventParamText('lots', 10),
                $this->eventParamText('margin_order', 1000),
                $this->eventParamText('trade_account_type', 0)
            ),
            $result['data'][0]['event_text']
        );
    }

    public function test_list_reads_relation_from_legacy_event_param_list(): void
    {
        $repo = Mockery::mock(BadgeRepository::class);
        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $service = new BadgeService($repo, $copyTradingRepo);

        $repo->shouldReceive('getList')
            ->once()
            ->with('', null, 20, 0)
            ->andReturn([[
                [
                    'id' => 10,
                    'icon' => '',
                    'short_name' => 'closer',
                    'event_key' => 'close_trade',
                    'event_params' => [
                        ['param_key' => 'trade_account_type', 'param_value' => '1'],
                        ['param_key' => 'relation', 'param_value' => 'and'],
                    ],
                    'status' => Badge::STATUS_ENABLED,
                    'name' => ['en_US' => 'Close trader'],
                    'description' => [],
                ],
            ], 1]);

        $result = $service->getList('', null, 20, 0);

        $this->assertSame(['trade_account_type' => 1], $result['data'][0]['event_params']);
        $this->assertSame('and', $result['data'][0]['relation']);
    }

    public function test_update_clears_stale_params_when_event_key_changes(): void
    {
        $repo = Mockery::mock(BadgeRepository::class);
        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $service = new BadgeService($repo, $copyTradingRepo);
        $existing = $this->makeBadge([
            'id' => 10,
            'short_name' => 'closer',
            'event_key' => 'close_trade',
            'event_params' => [
                'trade_account_type' => 1,
                'lots' => 1,
                'relation' => 'or',
            ],
            'name' => ['en_US' => 'Close trader'],
            'description' => [],
        ]);
        $updated = $this->makeBadge([
            'id' => 10,
            'short_name' => 'closer',
            'event_key' => 'registered',
            'event_params' => [],
            'name' => ['en_US' => 'Close trader'],
            'description' => [],
        ]);

        $repo->shouldReceive('findById')->once()->with(10)->andReturn($existing);
        $repo->shouldReceive('existsByShortName')->once()->with('closer', 10)->andReturnFalse();
        $repo->shouldReceive('existsByName')->once()->with(['Close trader'], 10)->andReturnFalse();
        $repo->shouldReceive('updateById')
            ->once()
            ->with(
                10,
                Mockery::on(function (array $data): bool {
                    $this->assertSame([], $data['event_params']);

                    return true;
                }),
                $existing
            )
            ->andReturn($updated);

        $badge = $service->update(10, [
            'short_name' => 'closer',
            'event_key' => 'registered',
            'locale_configs' => [
                ['lang' => 'en_US', 'name' => 'Close trader'],
            ],
        ]);

        $this->assertIsArray($badge);
        $this->assertSame([], $badge['event_params']);
        $this->assertSame([], $badge['event_params_text']);
        $this->assertSame('', $badge['event_text']);
        $this->assertNull($badge['relation']);
    }

    public function test_create_stores_master_level_keys_without_empty_values(): void
    {
        $repo = Mockery::mock(BadgeRepository::class);
        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $service = new BadgeService($repo, $copyTradingRepo);

        $repo->shouldReceive('existsByName')->once()->with(['Master level badge'], null)->andReturnFalse();
        $repo->shouldReceive('existsByShortName')->once()->with('master', null)->andReturnFalse();
        $repo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data): bool {
                $this->assertSame(['1'], $data['event_params']);

                return true;
            }))
            ->andReturn($this->makeBadge([
                'id' => 11,
                'short_name' => 'master',
                'event_key' => Badge::EVENT_KEY_MASTER_LEVEL_CHANGED,
                'event_params' => ['1'],
                'name' => ['en_US' => 'Master level badge'],
                'description' => [],
            ]));
        $copyTradingRepo->shouldReceive('getEnabledMasterLevelParams')->once()->andReturn([
            ['level_pos' => 1, 'name' => ['en_US' => 'Rising Master']],
        ]);

        $badge = $service->create([
            'icon' => '',
            'short_name' => 'master',
            'event_key' => Badge::EVENT_KEY_MASTER_LEVEL_CHANGED,
            'event_params' => [
                ['param_key' => '1'],
            ],
            'locale_configs' => [
                ['lang' => 'en_US', 'name' => 'Master level badge'],
            ],
        ]);

        $this->assertSame(['1' => ''], $badge['event_params']);
        $this->assertSame('{"1":""}', json_encode($badge['event_params']));
        $this->assertSame(['Rising Master'], $badge['event_params_text']);
        $this->assertSame('Rising Master', $badge['event_text']);
    }

    public function test_list_returns_legacy_master_level_object_structure(): void
    {
        $repo = Mockery::mock(BadgeRepository::class);
        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $service = new BadgeService($repo, $copyTradingRepo);

        $repo->shouldReceive('getList')
            ->once()
            ->with('', null, 20, 0)
            ->andReturn([[[
                'id' => 11,
                'icon' => '',
                'short_name' => 'master',
                'event_key' => Badge::EVENT_KEY_MASTER_LEVEL_CHANGED,
                'event_params' => ['1' => ''],
                'status' => Badge::STATUS_ENABLED,
                'name' => ['en_US' => 'Master level badge'],
                'description' => [],
            ]], 1]);
        $copyTradingRepo->shouldReceive('getEnabledMasterLevelParams')->once()->andReturn([
            ['level_pos' => 1, 'name' => ['en_US' => 'Rising Master']],
        ]);

        $result = $service->getList('', null, 20, 0);

        $this->assertSame(['1' => ''], $result['data'][0]['event_params']);
        $this->assertSame('{"1":""}', json_encode($result['data'][0]['event_params']));
        $this->assertSame(['Rising Master'], $result['data'][0]['event_params_text']);
    }

    private function makeBadge(array $attributes): Badge
    {
        foreach (['event_params', 'name', 'description'] as $jsonField) {
            if (is_array($attributes[$jsonField] ?? null)) {
                $attributes[$jsonField] = json_encode($attributes[$jsonField]);
            }
        }

        $badge = new Badge;
        $badge->setRawAttributes(array_merge([
            'id' => 0,
            'icon' => '',
            'short_name' => '',
            'event_key' => 'registered',
            'event_params' => '[]',
            'status' => Badge::STATUS_ENABLED,
            'name' => '[]',
            'description' => '[]',
            'created_at' => null,
            'updated_at' => null,
        ], $attributes), true);

        return $badge;
    }

    private function eventParamText(string $paramKey, mixed $value): string
    {
        foreach (Badge::eventOptions() as $option) {
            if (($option['key'] ?? '') !== 'close_trade') {
                continue;
            }

            foreach ($option['event_params'] ?? [] as $param) {
                if (($param['param_key'] ?? '') !== $paramKey) {
                    continue;
                }

                $label = preg_replace('/^(?:[≥>]=?|=)\s*/u', '', (string) ($param['param_name'] ?? ''));
                foreach ($param['options'] ?? [] as $option) {
                    if ((string) ($option['value'] ?? '') === (string) $value) {
                        return sprintf('%s:%s', $label, $option['label']);
                    }
                }

                return sprintf('%s >= %s', $label, $value);
            }
        }

        $this->fail(sprintf('Event param [%s] was not found.', $paramKey));
    }
}
