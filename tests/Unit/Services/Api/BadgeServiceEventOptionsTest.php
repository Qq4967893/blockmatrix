<?php

namespace Tests\Unit\Services\Api;

use App\Constants\FieldType;
use App\Models\Api\Badge;
use App\Repositories\Api\BadgeRepository;
use App\Repositories\Api\CopyTradingRepository;
use App\Services\Api\BadgeService;
use Mockery;
use Scaffold\Framework\Enums\TypeBadgeEvent;
use Tests\TestCase;

class BadgeServiceEventOptionsTest extends TestCase
{
    public function test_event_options_follow_point_rule_structure_and_load_master_levels(): void
    {
        app()->setLocale('zh_CN');

        $badgeRepo = Mockery::mock(BadgeRepository::class);
        $copyTradingRepo = Mockery::mock(CopyTradingRepository::class);
        $copyTradingRepo->shouldReceive('getEnabledMasterLevelParams')
            ->once()
            ->andReturn([
                [
                    'level_pos' => 1,
                    'name' => ['zh_CN' => '新锐高手', 'en_US' => 'Rising Master'],
                ],
                [
                    'level_pos' => 3,
                    'name' => json_encode(['zh_CN' => '传奇高手', 'en_US' => 'Legendary Master']),
                ],
            ]);

        $service = new BadgeService($badgeRepo, $copyTradingRepo);
        $options = $service->getEventOptions();

        $registered = $this->findEventOption($options, 'registered');
        $this->assertSame(['key', 'name', 'event_params', 'relation', 'render_mode'], array_keys($registered));
        $this->assertSame([], $registered['relation']);
        $this->assertSame(2, $registered['render_mode']);
        $this->assertSame([
            'type',
            'param_key',
            'param_name',
            'is_required',
            'is_select_required',
        ], array_keys($registered['event_params'][0]));

        $closeTrade = $this->findEventOption($options, 'close_trade');
        $this->assertSame(['or', 'and'], $closeTrade['relation']);
        $this->assertSame(1, $closeTrade['render_mode']);
        $this->assertSame('trade_account_type', $closeTrade['event_params'][0]['param_key']);
        $this->assertSame((string) FieldType::SELECT, $closeTrade['event_params'][0]['type']);
        $this->assertTrue($closeTrade['event_params'][0]['is_required']);
        $this->assertTrue($closeTrade['event_params'][0]['is_select_required']);
        $scaffoldDetail = collect(TypeBadgeEvent::details())
            ->firstWhere('value', TypeBadgeEvent::CloseTrade->value);
        $expectedOptions = collect($scaffoldDetail['variable'] ?? [])
            ->firstWhere('variable', 'trade_account_type')['options'] ?? [];
        $this->assertSame($expectedOptions, $closeTrade['event_params'][0]['options']);

        $masterFollowed = $this->findEventOption($options, 'master_followed');
        $this->assertSame(2, $masterFollowed['render_mode']);

        $masterLevelChanged = $this->findEventOption(
            $options,
            Badge::EVENT_KEY_MASTER_LEVEL_CHANGED
        );
        $this->assertSame(1, $masterLevelChanged['render_mode']);
        $this->assertSame([], $masterLevelChanged['relation']);
        $this->assertSame([
            [
                'type' => (string) FieldType::NONE,
                'param_key' => '1',
                'param_name' => '新锐高手',
            ],
            [
                'type' => (string) FieldType::NONE,
                'param_key' => '3',
                'param_name' => '传奇高手',
            ],
        ], $masterLevelChanged['event_params']);

        $this->assertSame($options, $service->getEventOptions());
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function findEventOption(array $options, string $eventKey): array
    {
        foreach ($options as $option) {
            if (($option['key'] ?? '') === $eventKey) {
                return $option;
            }
        }

        $this->fail(sprintf('Event option [%s] was not found.', $eventKey));
    }
}
