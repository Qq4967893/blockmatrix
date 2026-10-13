<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\PositionRecord;
use App\Repositories\Api\PositionRecordRepository;
use App\Services\Api\PositionRecordStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PositionRecordStatusServiceTest extends TestCase
{
    private array $originalApiConnection;

    private PositionRecordStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalApiConnection = config('database.connections.'.DB_API);
        config([
            'database.connections.'.DB_API => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge(DB_API);

        Schema::connection(DB_API)->create('position_record', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('status')->default(PositionRecord::STATUS_ENABLED);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
        });

        $this->service = new PositionRecordStatusService(
            new PositionRecordRepository(new PositionRecord),
        );
    }

    protected function tearDown(): void
    {
        DB::purge(DB_API);
        config(['database.connections.'.DB_API => $this->originalApiConnection]);

        parent::tearDown();
    }

    public function test_resolve_status_uses_manual_switch_and_schedule_window(): void
    {
        $now = CarbonImmutable::parse('2026-08-27 10:00:00', 'UTC');

        $this->assertSame(PositionRecord::STATUS_DISABLED, $this->service->resolveStatus(
            PositionRecord::STATUS_DISABLED,
            '2026-08-27 11:00:00',
            '2026-08-27 12:00:00',
            $now,
        ));
        $this->assertSame(PositionRecord::STATUS_PENDING, $this->service->resolveStatus(
            PositionRecord::STATUS_ENABLED,
            '2026-08-27 11:00:00',
            '2026-08-27 12:00:00',
            $now,
        ));
        $this->assertSame(PositionRecord::STATUS_ENABLED, $this->service->resolveStatus(
            PositionRecord::STATUS_PENDING,
            '2026-08-27 10:00:00',
            '2026-08-27 11:00:00',
            $now,
        ));
        $this->assertSame(PositionRecord::STATUS_DISABLED, $this->service->resolveStatus(
            PositionRecord::STATUS_ENABLED,
            '2026-08-27 09:00:00',
            '2026-08-27 10:00:00',
            $now,
        ));
    }

    public function test_sync_activates_started_records_and_disables_expired_records(): void
    {
        DB::connection(DB_API)->table('position_record')->insert([
            ['id' => 1, 'status' => PositionRecord::STATUS_PENDING, 'start_time' => '2026-08-27 09:00:00', 'end_time' => '2026-08-27 11:00:00'],
            ['id' => 2, 'status' => PositionRecord::STATUS_PENDING, 'start_time' => '2026-08-27 11:00:00', 'end_time' => '2026-08-27 12:00:00'],
            ['id' => 3, 'status' => PositionRecord::STATUS_ENABLED, 'start_time' => '2026-08-27 08:00:00', 'end_time' => '2026-08-27 10:00:00'],
            ['id' => 4, 'status' => PositionRecord::STATUS_PENDING, 'start_time' => '2026-08-27 08:00:00', 'end_time' => '2026-08-27 09:00:00'],
            ['id' => 5, 'status' => PositionRecord::STATUS_DISABLED, 'start_time' => '2026-08-27 09:00:00', 'end_time' => '2026-08-27 11:00:00'],
        ]);

        $summary = $this->service->sync(CarbonImmutable::parse('2026-08-27 10:00:00', 'UTC'));

        $this->assertSame(['activated' => 1, 'disabled' => 2], $summary);
        $this->assertSame([
            1 => PositionRecord::STATUS_ENABLED,
            2 => PositionRecord::STATUS_PENDING,
            3 => PositionRecord::STATUS_DISABLED,
            4 => PositionRecord::STATUS_DISABLED,
            5 => PositionRecord::STATUS_DISABLED,
        ], DB::connection(DB_API)
            ->table('position_record')
            ->orderBy('id')
            ->pluck('status', 'id')
            ->map(fn ($status) => (int) $status)
            ->all());
    }
}
