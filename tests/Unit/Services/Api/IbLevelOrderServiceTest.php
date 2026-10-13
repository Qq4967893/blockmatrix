<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\IbLevel;
use App\Repositories\Api\IbAccountRepository;
use App\Repositories\Api\IbCommissionRuleRepository;
use App\Repositories\Api\IbLevelRepository;
use App\Services\Api\IbLevelOrderService;
use App\Services\Api\IbLevelService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\DataProvider;
use Scaffold\Framework\Models\Api\IbCommissionRule;
use Tests\TestCase;

class IbLevelOrderServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_plan_order_sorts_levels_by_effective_base_commission_rate(): void
    {
        $service = $this->makeService();

        $result = $service->planOrder([
            $this->level(3, 'AAA', 3, '40.0000'),
            $this->level(1, 'A', 2, '20.0000'),
            $this->level(2, 'DIY', 1, '10.0000'),
        ]);

        $this->assertSame([2, 1, 3], array_map(
            static fn (array $level): int => (int) $level['id'],
            $result
        ));
        $this->assertSame([1, 2, 3], array_map(
            static fn (array $level): int => (int) $level['new_level_pos'],
            $result
        ));
        $this->assertSame([1, 0, 0], array_map(
            static fn (array $level): int => (int) $level['new_is_root'],
            $result
        ));
    }

    #[DataProvider('insertPositionProvider')]
    public function test_resolve_insert_position(string $rate, int $expectedPosition): void
    {
        $service = $this->makeService();

        $levels = [
            $this->level(1, 'A', 1, '10.0000'),
            $this->level(2, 'AA', 2, '20.0000'),
            $this->level(3, 'AAA', 3, '40.0000'),
        ];

        $this->assertSame($expectedPosition, $service->resolveInsertPosition($levels, $rate));
    }

    /**
     * @return array<int, array{0:string,1:int}>
     */
    public static function insertPositionProvider(): array
    {
        return [
            ['5.0000', 1],
            ['15.0000', 2],
            ['30.0000', 3],
            ['50.0000', 4],
        ];
    }

    public function test_plan_order_rejects_duplicate_rates(): void
    {
        $service = $this->makeService();

        $this->expectException(\Exception::class);
        $service->planOrder([
            $this->level(1, 'A', 1, '20.0000'),
            $this->level(2, 'DIY', 2, '20.0000'),
        ]);
    }

    public function test_sync_rewrites_positions_and_moves_root_rule(): void
    {
        $this->createSqliteApiTables();

        $now = now()->format('Y-m-d H:i:s');
        DB::connection(DB_API)->table('ib_commission_rule')->insert([
            ['id' => 1, 'name' => '{"en_US":"A Rule"}', 'current_version_id' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => '{"en_US":"AA Rule"}', 'current_version_id' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => '{"en_US":"DIY Rule"}', 'current_version_id' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::connection(DB_API)->table('ib_commission_rule_version')->insert([
            ['id' => 1, 'rule_id' => 1, 'version_no' => 1, 'base_commission_rate' => '10.0000', 'status' => 2],
            ['id' => 2, 'rule_id' => 2, 'version_no' => 1, 'base_commission_rate' => '20.0000', 'status' => 2],
            ['id' => 3, 'rule_id' => 3, 'version_no' => 1, 'base_commission_rate' => '30.0000', 'status' => 2],
        ]);
        DB::connection(DB_API)->table('ib_level')->insert([
            ['id' => 1, 'name' => '{"en_US":"A"}', 'level_pos' => 1, 'is_root' => 1, 'active_trader_threshold' => 1, 'team_position_size_threshold' => '1.000000', 'commission_rule_id' => 1, 'status' => 1],
            ['id' => 2, 'name' => '{"en_US":"DIY"}', 'level_pos' => 2, 'is_root' => 0, 'active_trader_threshold' => 3, 'team_position_size_threshold' => '3.000000', 'commission_rule_id' => 3, 'status' => 1],
            ['id' => 3, 'name' => '{"en_US":"AA"}', 'level_pos' => 3, 'is_root' => 0, 'active_trader_threshold' => 2, 'team_position_size_threshold' => '2.000000', 'commission_rule_id' => 2, 'status' => 1],
        ]);

        $repository = new IbLevelRepository(new IbLevel);
        DB::connection(DB_API)->transaction(function () use ($repository): void {
            (new IbLevelOrderService($repository))->sync();
        });

        $levels = DB::connection(DB_API)->table('ib_level')
            ->orderBy('level_pos')
            ->get(['id', 'level_pos', 'is_root', 'active_trader_threshold', 'team_position_size_threshold']);

        $this->assertSame(
            [
                ['id' => 1, 'level_pos' => 1, 'is_root' => 1, 'active_trader_threshold' => 1, 'team_position_size_threshold' => '1.000000'],
                ['id' => 3, 'level_pos' => 2, 'is_root' => 0, 'active_trader_threshold' => 2, 'team_position_size_threshold' => '2.000000'],
                ['id' => 2, 'level_pos' => 3, 'is_root' => 0, 'active_trader_threshold' => 3, 'team_position_size_threshold' => '3.000000'],
            ],
            $levels->map(static fn ($level): array => [
                'id' => (int) $level->id,
                'level_pos' => (int) $level->level_pos,
                'is_root' => (int) $level->is_root,
                'active_trader_threshold' => (int) $level->active_trader_threshold,
                'team_position_size_threshold' => number_format((float) $level->team_position_size_threshold, 6, '.', ''),
            ])->all()
        );
    }

    public function test_level_store_allows_threshold_equal_to_next_level(): void
    {
        $this->createSqliteApiTables();

        $now = now()->format('Y-m-d H:i:s');
        DB::connection(DB_API)->table('ib_commission_rule')->insert([
            ['id' => 1, 'name' => '{"en_US":"A Rule"}', 'current_version_id' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => '{"en_US":"AA Rule"}', 'current_version_id' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => '{"en_US":"DIY Rule"}', 'current_version_id' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::connection(DB_API)->table('ib_commission_rule_version')->insert([
            ['id' => 1, 'rule_id' => 1, 'version_no' => 1, 'base_commission_rate' => '10.0000', 'status' => 2],
            ['id' => 2, 'rule_id' => 2, 'version_no' => 1, 'base_commission_rate' => '30.0000', 'status' => 2],
            ['id' => 3, 'rule_id' => 3, 'version_no' => 1, 'base_commission_rate' => '20.0000', 'status' => 2],
        ]);
        DB::connection(DB_API)->table('ib_level')->insert([
            ['id' => 1, 'name' => '{"en_US":"A"}', 'level_pos' => 1, 'is_root' => 1, 'active_trader_threshold' => 1, 'team_position_size_threshold' => '1.000000', 'commission_rule_id' => 1, 'status' => 1],
            ['id' => 2, 'name' => '{"en_US":"AA"}', 'level_pos' => 2, 'is_root' => 0, 'active_trader_threshold' => 3, 'team_position_size_threshold' => '3.000000', 'commission_rule_id' => 2, 'status' => 1],
        ]);

        $levelRepository = new IbLevelRepository(new IbLevel);
        $service = new IbLevelService(
            $levelRepository,
            new IbCommissionRuleRepository(new IbCommissionRule),
            Mockery::mock(IbAccountRepository::class),
            new IbLevelOrderService($levelRepository)
        );

        $result = $service->store([
            'locale_configs' => [
                ['lang' => 'en_US', 'name' => 'DIY'],
            ],
            'commission_rule_id' => 3,
            'is_initial' => 0,
            'active_trader_threshold' => 3,
            'team_position_size_threshold' => '3',
        ]);

        $this->assertTrue($result['is_succ']);
        $levels = DB::connection(DB_API)->table('ib_level')
            ->orderBy('level_pos')
            ->pluck('name', 'level_pos');

        $this->assertSame(
            [1 => '{"en_US":"A"}', 2 => '{"en_US":"DIY"}', 3 => '{"en_US":"AA"}'],
            $levels->all()
        );
    }

    private function makeService(): IbLevelOrderService
    {
        return new IbLevelOrderService(Mockery::mock(IbLevelRepository::class));
    }

    private function createSqliteApiTables(): void
    {
        config(['database.connections.api' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge(DB_API);

        Schema::connection(DB_API)->create('ib_commission_rule', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('current_version_id')->nullable();
            $table->timestamps();
        });
        Schema::connection(DB_API)->create('ib_commission_rule_version', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('rule_id');
            $table->unsignedInteger('version_no');
            $table->decimal('base_commission_rate', 12, 4);
            $table->decimal('promotion_commission_rate', 12, 4)->default(0);
            $table->integer('status');
            $table->timestamps();
        });
        Schema::connection(DB_API)->create('ib_level', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->integer('level_pos')->unique();
            $table->boolean('is_root')->default(false);
            $table->boolean('is_initial')->default(false);
            $table->integer('active_trader_threshold')->default(0);
            $table->decimal('team_position_size_threshold', 18, 6)->default(0);
            $table->unsignedInteger('commission_rule_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    private function level(int $id, string $name, int $levelPos, string $rate): array
    {
        return [
            'id' => $id,
            'name' => ['en_US' => $name],
            'level_pos' => $levelPos,
            'is_root' => $levelPos === 1 ? 1 : 0,
            'active_trader_threshold' => $levelPos,
            'team_position_size_threshold' => sprintf('%d.000000', $levelPos),
            'base_commission_rate' => $rate,
            'commission_version_status' => IbCommissionRuleRepository::STATUS_APPROVED,
        ];
    }
}
