<?php

namespace Tests\Unit\Repositories\Api;

use App\Repositories\Api\TradeAccountRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Scaffold\Framework\Enums\TypeTradeAccount;
use Scaffold\Framework\Models\Api\TradeAccount;
use Tests\TestCase;

class TradeAccountRepositoryTest extends TestCase
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

        Schema::connection(DB_API)->create('trade_account', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_code');
            $table->unsignedTinyInteger('trade_account_type');
            $table->unsignedTinyInteger('status')->default(1);
        });
    }

    protected function tearDown(): void
    {
        DB::purge(DB_API);
        config(['database.connections.'.DB_API => $this->originalApiConnection]);

        parent::tearDown();
    }

    public function test_exists_bonus_fund_by_user_code_matches_user_and_account_type(): void
    {
        DB::connection(DB_API)->table('trade_account')->insert([
            [
                'user_code' => 780889597,
                'trade_account_type' => TypeTradeAccount::UTA->value,
                'status' => 1,
            ],
            [
                'user_code' => 123456789,
                'trade_account_type' => TypeTradeAccount::BonusFund->value,
                'status' => 1,
            ],
        ]);

        $repository = new TradeAccountRepository(new TradeAccount);

        self::assertFalse($repository->existsBonusFundByUserCode(780889597));

        DB::connection(DB_API)->table('trade_account')->insert([
            'user_code' => 780889597,
            'trade_account_type' => TypeTradeAccount::BonusFund->value,
            'status' => 0,
        ]);

        self::assertTrue($repository->existsBonusFundByUserCode(780889597));
    }
}
