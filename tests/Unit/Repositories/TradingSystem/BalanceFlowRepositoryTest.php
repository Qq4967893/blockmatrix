<?php

namespace Tests\Unit\Repositories\TradingSystem;

use App\Repositories\Api\BalanceFlowRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Scaffold\Framework\Enums\TypeLedger;
use Scaffold\Framework\Models\TradingSystem\BalanceFlow;
use Tests\TestCase;

class BalanceFlowRepositoryTest extends TestCase
{
    private array $originalTradingSystemConnection;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('zh_CN');
        $this->originalTradingSystemConnection = config('database.connections.'.DB_TS);
        config([
            'database.connections.'.DB_TS => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        DB::purge(DB_TS);
        DB::connection(DB_TS)->statement('attach database ":memory:" as api');

        Schema::connection(DB_TS)->create('balance_flow', function (Blueprint $table): void {
            $table->increments('id');
            $table->bigInteger('login');
            $table->string('ref_ticket')->default('');
            $table->unsignedTinyInteger('fee_category')->default(0);
            $table->unsignedTinyInteger('fee_category_sub')->default(0);
            $table->decimal('amount', 32, 6)->default(0);
            $table->decimal('balance', 32, 6)->default(0);
            $table->timestamp('created_at')->nullable();
        });

        Schema::connection(DB_TS)->create('api.trade_account', function (Blueprint $table): void {
            $table->increments('id');
            $table->bigInteger('trade_account_id');
            $table->unsignedTinyInteger('trade_account_type')->default(0);
        });
    }

    protected function tearDown(): void
    {
        Schema::connection(DB_TS)->drop('balance_flow');
        Schema::connection(DB_TS)->drop('api.trade_account');
        DB::connection(DB_TS)->statement('detach database api');
        DB::purge(DB_TS);
        config(['database.connections.'.DB_TS => $this->originalTradingSystemConnection]);

        parent::tearDown();
    }

    public function test_list_selects_fee_category_sub_so_credit_unlock_is_not_labelled_as_deposit(): void
    {
        DB::connection(DB_TS)->table('balance_flow')->insert([
            'id' => 1,
            'login' => 90001,
            'ref_ticket' => 'PB117888585459630100001',
            'fee_category' => 4,
            'fee_category_sub' => TypeLedger::UnlockCredit->value,
            'amount' => 5,
            'balance' => 6818.452585,
            'created_at' => '2026-09-08 09:09:06',
        ]);
        DB::connection(DB_TS)->table('api.trade_account')->insert([
            'id' => 1,
            'trade_account_id' => 90001,
            'trade_account_type' => 0,
        ]);

        [$records, $count] = (new BalanceFlowRepository(new BalanceFlow))
            ->paginateByLogins([90001], [], 20, 0);

        $this->assertSame(1, $count);
        $this->assertSame(TypeLedger::UnlockCredit->value, (int) $records[0]['fee_category_sub']);
        $this->assertSame('信用赠金解锁', $records[0]['fee_category_text']);
    }

    public function test_list_filters_by_credit_unlock_sub_type(): void
    {
        DB::connection(DB_TS)->table('balance_flow')->insert([
            $this->flow(id: 1, feeSubType: TypeLedger::UnlockCredit->value),
            $this->flow(id: 2, feeSubType: 0),
            $this->flow(id: 3, feeSubType: 14),
        ]);
        DB::connection(DB_TS)->table('api.trade_account')->insert([
            'id' => 1,
            'trade_account_id' => 90001,
            'trade_account_type' => 0,
        ]);

        [$records, $count] = (new BalanceFlowRepository(new BalanceFlow))
            ->paginateByLogins(
                [90001],
                [
                    'fund_type' => 4,
                    'fund_sub_type' => TypeLedger::UnlockCredit->value,
                ],
                20,
                0,
            );

        $this->assertSame(1, $count);
        $this->assertSame([1], array_column($records, 'id'));
    }

    private function flow(int $id, int $feeSubType): array
    {
        return [
            'id' => $id,
            'login' => 90001,
            'ref_ticket' => "PB-{$id}",
            'fee_category' => 4,
            'fee_category_sub' => $feeSubType,
            'amount' => 5,
            'balance' => 100 + $id,
            'created_at' => "2026-09-08 09:09:0{$id}",
        ];
    }
}
