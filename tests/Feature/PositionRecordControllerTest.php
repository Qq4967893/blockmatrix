<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\PositionRecordController;
use App\Http\Requests\Entries\PositionEntriesRequest;
use App\Models\Api\PositionRecord;
use App\Services\Api\PositionEntryRecordService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class PositionRecordControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_options_include_pending_status(): void
    {
        $response = $this->app->make(PositionRecordController::class)->options();

        $this->assertSame(PositionRecord::statusOptions(), $response->getData(true)['data']['status']);
    }

    public function test_changing_schedule_recalculates_a_disabled_record_status(): void
    {
        $service = Mockery::mock(PositionEntryRecordService::class);
        $service->shouldReceive('recordInfoById')->once()->with(12)->andReturn((object) [
            'id' => 12,
            'identifier' => 'home_banner',
            'platform' => 'ios',
            'status' => PositionRecord::STATUS_DISABLED,
            'start_time' => '2026-08-28 00:00:00',
            'end_time' => '2026-08-29 00:00:00',
        ]);
        $service->shouldReceive('entryRecordList')->once()->andReturn([]);
        $service->shouldReceive('update')
            ->once()
            ->withArgs(fn (...$args) => $args[9] === PositionRecord::STATUS_ENABLED)
            ->andReturnTrue();

        $request = PositionEntriesRequest::create('/api/position_record/update', 'POST', [
            'record_id' => 12,
            'identifier' => 'home_banner',
            'platform' => ['ios'],
            'name' => 'Home banner',
            'description' => 'Home banner description',
            'locale_configs' => [[
                'platform' => 'ios',
                'lang' => 'en_US',
                'img_url' => 'https://example.com/banner.png',
                'link' => 'https://example.com',
            ]],
            'start_time' => '2026-08-27 00:00:00',
            'end_time' => '2026-08-28 00:00:00',
            'status' => PositionRecord::STATUS_DISABLED,
        ]);

        $response = (new PositionRecordController($service))->update($request);

        $this->assertTrue($response->getData(true)['is_succ']);
    }
}
