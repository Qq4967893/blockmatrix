<?php

namespace Tests\Unit\Repositories\Api;

use App\Repositories\Api\IbCommissionRuleRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Scaffold\Framework\Models\Api\IbCommissionRule;
use Tests\TestCase;

class IbCommissionRuleRepositoryTest extends TestCase
{
    private array $originalApiConnection;

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

        $this->createTables();
    }

    protected function tearDown(): void
    {
        DB::purge(DB_API);
        config(['database.connections.'.DB_API => $this->originalApiConnection]);

        parent::tearDown();
    }

    public function test_list_orders_by_base_commission_rate_and_tie_breaks_by_id(): void
    {
        $now = '2026-09-10 12:00:00';

        foreach ([
            ['id' => 1, 'rate' => 3],
            ['id' => 2, 'rate' => 1],
            ['id' => 3, 'rate' => 2],
            ['id' => 4, 'rate' => 2],
        ] as $rule) {
            DB::connection(DB_API)->table('ib_commission_rule')->insert([
                'id' => $rule['id'],
                'name' => json_encode(['zh_CN' => "rule-{$rule['id']}"], JSON_THROW_ON_ERROR),
                'current_version_id' => $rule['id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::connection(DB_API)->table('ib_commission_rule_version')->insert([
                'id' => $rule['id'],
                'rule_id' => $rule['id'],
                'version_no' => 1,
                'base_commission_rate' => $rule['rate'],
                'promotion_commission_rate' => 0,
                'status' => IbCommissionRuleRepository::STATUS_DRAFT,
                'rejection_reason' => null,
                'reviewed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        [$rows, $count] = (new IbCommissionRuleRepository(new IbCommissionRule))
            ->getList([], 20, 0);

        $this->assertSame(4, $count);
        $this->assertSame(
            [2, 4, 3, 1],
            array_map(static fn (array $row): int => (int) $row['id'], $rows),
        );
    }

    private function createTables(): void
    {
        Schema::connection(DB_API)->create('ib_commission_rule', function (Blueprint $table): void {
            $table->id();
            $table->json('name');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
        });

        Schema::connection(DB_API)->create('ib_commission_rule_version', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('rule_id');
            $table->unsignedInteger('version_no');
            $table->decimal('base_commission_rate', 12, 4);
            $table->decimal('promotion_commission_rate', 12, 4);
            $table->tinyInteger('status');
            $table->string('rejection_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }
}
