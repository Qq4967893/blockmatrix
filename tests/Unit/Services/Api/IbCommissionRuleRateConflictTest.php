<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\IbLevel;
use App\Repositories\Api\IbCommissionRuleDetailRepository;
use App\Repositories\Api\IbCommissionRuleRepository;
use App\Repositories\Api\IbLevelRepository;
use App\Services\Api\IbCommissionRuleService;
use App\Services\Api\IbCommissionSourceDetailFormatter;
use App\Services\Api\IbLevelOrderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;
use Scaffold\Framework\Models\Api\IbCommissionRule;
use Tests\TestCase;

class IbCommissionRuleRateConflictTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_base_commission_rate_conflict_returns_validation_error(): void
    {
        $service = $this->makeService($this->mockRuleRepository(
            conflict: (object) [
                'id' => 2,
                'name' => '{"en_US":"AA"}',
                'base_commission_rate' => '20.0000',
            ]
        ));
        $method = $this->getMethod($service, 'assertBaseCommissionRateAvailable');

        try {
            $method->invoke($service, '20.0000');
            $this->fail('Expected a base commission rate conflict.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['base_commission_rate' => ['Base commission rate already exists. AA current rate: 20%']],
                $exception->errors()
            );
        }
    }

    public function test_base_commission_rate_without_conflict_passes(): void
    {
        $service = $this->makeService($this->mockRuleRepository());
        $method = $this->getMethod($service, 'assertBaseCommissionRateAvailable');

        $method->invoke($service, '30.0000', 1);

        $this->assertTrue(true);
    }

    public function test_review_approval_reorders_linked_levels(): void
    {
        $this->createSqliteApiTables();
        $now = now()->format('Y-m-d H:i:s');

        DB::connection(DB_API)->table('ib_commission_rule')->insert([
            ['id' => 1, 'name' => '{"en_US":"A Rule"}', 'current_version_id' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => '{"en_US":"DIY Rule"}', 'current_version_id' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::connection(DB_API)->table('ib_commission_rule_version')->insert([
            ['id' => 1, 'rule_id' => 1, 'version_no' => 1, 'base_commission_rate' => '20.0000', 'status' => 2],
            ['id' => 2, 'rule_id' => 1, 'version_no' => 2, 'base_commission_rate' => '5.0000', 'status' => 1],
            ['id' => 3, 'rule_id' => 2, 'version_no' => 1, 'base_commission_rate' => '10.0000', 'status' => 2],
        ]);
        DB::connection(DB_API)->table('ib_level')->insert([
            ['id' => 1, 'name' => '{"en_US":"DIY"}', 'level_pos' => 1, 'is_root' => 1, 'is_initial' => 1, 'active_trader_threshold' => 1, 'team_position_size_threshold' => '1.000000', 'commission_rule_id' => 2, 'status' => 1],
            ['id' => 2, 'name' => '{"en_US":"A"}', 'level_pos' => 2, 'is_root' => 0, 'is_initial' => 0, 'active_trader_threshold' => 2, 'team_position_size_threshold' => '2.000000', 'commission_rule_id' => 1, 'status' => 1],
        ]);

        $levelRepository = new IbLevelRepository(new IbLevel);
        $service = new IbCommissionRuleService(
            new IbCommissionRuleRepository(new IbCommissionRule),
            Mockery::mock(IbCommissionRuleDetailRepository::class),
            Mockery::mock(IbCommissionSourceDetailFormatter::class),
            new IbLevelOrderService($levelRepository)
        );

        $service->review(1, IbCommissionRuleRepository::REVIEW_ACTION_APPROVE, null);

        $levels = DB::connection(DB_API)->table('ib_level')
            ->orderBy('level_pos')
            ->get(['name', 'level_pos', 'is_root', 'active_trader_threshold']);

        $this->assertSame(
            [
                ['name' => '{"en_US":"A"}', 'level_pos' => 1, 'is_root' => 1, 'active_trader_threshold' => 1],
                ['name' => '{"en_US":"DIY"}', 'level_pos' => 2, 'is_root' => 0, 'active_trader_threshold' => 1],
            ],
            $levels->map(static fn ($level): array => (array) $level)->all()
        );
    }

    private function mockRuleRepository(?object $conflict = null): IbCommissionRuleRepository
    {
        $repository = Mockery::mock(IbCommissionRuleRepository::class);
        $repository->shouldReceive('findEffectiveBaseCommissionRateConflict')
            ->andReturn($conflict);

        if ($conflict === null) {
            $repository->shouldReceive('findPendingBaseCommissionRateConflict')
                ->andReturn(null);
        }

        return $repository;
    }

    private function makeService(IbCommissionRuleRepository $ruleRepository): IbCommissionRuleService
    {
        return new IbCommissionRuleService(
            $ruleRepository,
            Mockery::mock(IbCommissionRuleDetailRepository::class),
            Mockery::mock(IbCommissionSourceDetailFormatter::class),
            Mockery::mock(IbLevelOrderService::class)
        );
    }

    private function getMethod(object $object, string $method): \ReflectionMethod
    {
        $reflection = new ReflectionClass($object);
        $methodReflection = $reflection->getMethod($method);
        $methodReflection->setAccessible(true);

        return $methodReflection;
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
            $table->string('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('submitted_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->string('remark')->nullable();
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
}
