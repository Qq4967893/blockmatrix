<?php

namespace Tests\Feature;

use App\Services\Api\IbAccountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IbAccountSelfCommissionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection(DB_API)->create('ib_account', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_code');
            $table->unsignedInteger('parent_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_self_commission_enabled')->default(false);
            $table->unsignedInteger('level_id')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('level_protection_started_at')->nullable();
            $table->timestamp('level_protection_ended_at')->nullable();
            $table->timestamps();
        });

        Schema::connection(DB_API)->create('ib_level', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('commission_rule_id')->nullable();
        });

        Schema::connection(DB_API)->create('ib_commission_rule', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('current_version_id')->nullable();
        });

        Schema::connection(DB_API)->create('ib_commission_rule_version', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('status')->default(0);
        });

        Schema::connection(DB_API)->create('ib_stat_snapshot', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_code');
            $table->unsignedInteger('ib_count')->default(0);
            $table->unsignedInteger('trader_count')->default(0);
            $table->decimal('team_position_size', 18, 6)->default(0);
        });

        $now = now()->format('Y-m-d H:i:s');
        DB::connection(DB_API)->table('ib_account')->insert([
            'user_code' => 1000001,
            'parent_id' => null,
            'is_primary' => 1,
            'is_self_commission_enabled' => 0,
            'level_id' => null,
            'activated_at' => $now,
            'level_protection_started_at' => null,
            'level_protection_ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_list_returns_self_commission_field_and_text(): void
    {
        $result = $this->app->make(IbAccountService::class)->getList([], 20, 0);

        $this->assertSame(0, $result['data'][0]['is_self_commission_enabled']);
        $this->assertSame(trans('user.boolean.no'), $result['data'][0]['is_self_commission_enabled_text']);
    }

    public function test_updates_self_commission_for_one_ib_account(): void
    {
        Queue::fake();

        $result = $this->app->make(IbAccountService::class)->updateSelfCommission('000001', 1);

        $this->assertSame('000001', $result['ib_id']);
        $this->assertSame(1, $result['is_self_commission_enabled']);
        $this->assertSame(trans('user.boolean.yes'), $result['is_self_commission_enabled_text']);
        $this->assertSame(
            1,
            DB::connection(DB_API)->table('ib_account')->where('id', 1)->value('is_self_commission_enabled')
        );
    }
}
