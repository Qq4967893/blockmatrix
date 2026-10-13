<?php

namespace Tests\Unit\Repositories\Api;

use App\Models\Api\CommunityActivity;
use App\Repositories\Api\CommunityActivityRepository;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommunityActivityRepositoryTest extends TestCase
{
    private CommunityActivityRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('sqlite')->create('community_activity', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedTinyInteger('status');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->timestamp('apply_end_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        $model = new class extends CommunityActivity
        {
            protected $connection = 'sqlite';
        };
        $this->repository = new CommunityActivityRepository($model);
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlite')->dropIfExists('community_activity');

        parent::tearDown();
    }

    public function test_list_puts_voided_activities_last_and_orders_each_group_by_descending_id(): void
    {
        DB::connection('sqlite')->table('community_activity')->insert([
            ['id' => 1, 'status' => CommunityActivity::STATUS_PENDING, 'start_at' => '2026-12-01 00:00:00', 'end_at' => '2026-12-01 01:00:00', 'apply_end_at' => '2026-12-01 00:30:00'],
            ['id' => 2, 'status' => CommunityActivity::STATUS_VOIDED, 'start_at' => '2026-12-02 00:00:00', 'end_at' => '2026-12-02 01:00:00', 'apply_end_at' => '2026-12-02 00:30:00'],
            ['id' => 3, 'status' => CommunityActivity::STATUS_ONLINE, 'start_at' => '2026-01-01 00:00:00', 'end_at' => '2026-01-01 01:00:00', 'apply_end_at' => '2026-01-01 00:30:00'],
            ['id' => 4, 'status' => CommunityActivity::STATUS_VOIDED, 'start_at' => '2026-01-02 00:00:00', 'end_at' => '2026-01-02 01:00:00', 'apply_end_at' => '2026-01-02 00:30:00'],
            ['id' => 5, 'status' => CommunityActivity::STATUS_PENDING, 'start_at' => '2026-06-01 00:00:00', 'end_at' => '2026-06-01 01:00:00', 'apply_end_at' => '2026-06-01 00:30:00'],
        ]);

        [$rows, $count] = $this->repository->getList(
            '',
            null,
            null,
            null,
            20,
            0,
            Carbon::parse('2026-01-01 00:30:00')
        );

        $this->assertSame(5, $count);
        $this->assertSame([5, 3, 1, 4, 2], $rows->modelKeys());
    }

    public function test_list_filters_by_effective_status_derived_from_activity_time(): void
    {
        $now = Carbon::parse('2026-09-12 12:00:00');
        DB::connection('sqlite')->table('community_activity')->insert([
            ['id' => 1, 'status' => CommunityActivity::STATUS_PENDING, 'start_at' => '2026-09-12 14:00:00', 'end_at' => '2026-09-12 15:00:00', 'apply_end_at' => '2026-09-12 14:30:00'],
            ['id' => 2, 'status' => CommunityActivity::STATUS_PENDING, 'start_at' => '2026-09-12 10:00:00', 'end_at' => '2026-09-12 13:00:00', 'apply_end_at' => '2026-09-12 12:30:00'],
            ['id' => 3, 'status' => CommunityActivity::STATUS_ONLINE, 'start_at' => '2026-09-12 11:00:00', 'end_at' => '2026-09-12 14:00:00', 'apply_end_at' => '2026-09-12 13:30:00'],
            ['id' => 4, 'status' => CommunityActivity::STATUS_ONLINE, 'start_at' => '2026-09-12 10:00:00', 'end_at' => '2026-09-12 11:00:00', 'apply_end_at' => '2026-09-12 10:30:00'],
            ['id' => 5, 'status' => CommunityActivity::STATUS_VOIDED, 'start_at' => '2026-09-12 14:00:00', 'end_at' => '2026-09-12 15:00:00', 'apply_end_at' => '2026-09-12 14:30:00'],
        ]);

        [$pendingRows, $pendingCount] = $this->repository->getList('', null, CommunityActivity::STATUS_PENDING, null, 20, 0, $now);
        [$onlineRows, $onlineCount] = $this->repository->getList('', null, CommunityActivity::STATUS_ONLINE, null, 20, 0, $now);
        [$voidedRows, $voidedCount] = $this->repository->getList('', null, CommunityActivity::STATUS_VOIDED, null, 20, 0, $now);

        $this->assertSame(1, $pendingCount);
        $this->assertSame([1], $pendingRows->modelKeys());
        $this->assertSame(2, $onlineCount);
        $this->assertSame([3, 2], $onlineRows->modelKeys());
        $this->assertSame(2, $voidedCount);
        $this->assertSame([5, 4], $voidedRows->modelKeys());
    }

    public function test_list_treats_manually_online_future_activity_as_online(): void
    {
        $now = Carbon::parse('2026-09-12 12:00:00');
        DB::connection('sqlite')->table('community_activity')->insert([
            ['id' => 1, 'status' => CommunityActivity::STATUS_ONLINE, 'start_at' => '2026-09-12 14:00:00', 'end_at' => '2026-09-12 15:00:00', 'apply_end_at' => '2026-09-12 14:30:00'],
            ['id' => 2, 'status' => CommunityActivity::STATUS_PENDING, 'start_at' => '2026-09-12 14:00:00', 'end_at' => '2026-09-12 15:00:00', 'apply_end_at' => '2026-09-12 14:30:00'],
        ]);

        [$pendingRows, $pendingCount] = $this->repository->getList('', null, CommunityActivity::STATUS_PENDING, null, 20, 0, $now);
        [$onlineRows, $onlineCount] = $this->repository->getList('', null, CommunityActivity::STATUS_ONLINE, null, 20, 0, $now);

        $this->assertSame(1, $pendingCount);
        $this->assertSame([2], $pendingRows->modelKeys());
        $this->assertSame(1, $onlineCount);
        $this->assertSame([1], $onlineRows->modelKeys());
    }

    public function test_status_refresh_publishes_active_activities_and_voids_expired_activities(): void
    {
        $now = Carbon::parse('2026-09-12 12:00:00');
        DB::connection('sqlite')->table('community_activity')->insert([
            ['id' => 1, 'status' => CommunityActivity::STATUS_PENDING, 'start_at' => '2026-09-12 10:00:00', 'end_at' => '2026-09-12 11:00:00', 'apply_end_at' => '2026-09-12 10:30:00'],
            ['id' => 2, 'status' => CommunityActivity::STATUS_PENDING, 'start_at' => '2026-09-12 12:00:00', 'end_at' => '2026-09-12 13:00:00', 'apply_end_at' => '2026-09-12 12:30:00'],
            ['id' => 3, 'status' => CommunityActivity::STATUS_PENDING, 'start_at' => '2026-09-12 14:00:00', 'end_at' => '2026-09-12 15:00:00', 'apply_end_at' => '2026-09-12 14:30:00'],
            ['id' => 4, 'status' => CommunityActivity::STATUS_VOIDED, 'start_at' => '2026-09-12 10:00:00', 'end_at' => '2026-09-12 11:00:00', 'apply_end_at' => '2026-09-12 10:30:00'],
        ]);

        $summary = $this->repository->updateStatusesByActivityTime($now);
        $statuses = DB::connection('sqlite')->table('community_activity')->orderBy('id')->pluck('status');

        $this->assertSame(['voided' => 1, 'online' => 1], $summary);
        $this->assertSame([
            CommunityActivity::STATUS_VOIDED,
            CommunityActivity::STATUS_ONLINE,
            CommunityActivity::STATUS_PENDING,
            CommunityActivity::STATUS_VOIDED,
        ], $statuses->all());

        $this->assertSame(['voided' => 0, 'online' => 0], $this->repository->updateStatusesByActivityTime($now));
    }
}
