<?php

namespace Tests\Unit\Repositories\Api;

use App\Repositories\Api\UserCreditLedgerRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Scaffold\Framework\Enums\TypeCreditLedger;
use Scaffold\Framework\Models\Api\UserCreditLedger;
use Tests\TestCase;

class UserCreditLedgerRepositoryTest extends TestCase
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
            ],
        ]);
        DB::purge(DB_API);

        Schema::connection(DB_API)->create('user_credit_ledger', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_credit_record_id');
            $table->unsignedBigInteger('user_code');
            $table->unsignedBigInteger('trade_account_id');
            $table->unsignedTinyInteger('type');
            $table->decimal('amount', 18, 6)->default(0);
            $table->decimal('balance_after', 18, 6)->nullable();
            $table->string('ticket')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection(DB_API)->drop('user_credit_ledger');
        DB::purge(DB_API);
        config(['database.connections.'.DB_API => $this->originalApiConnection]);

        parent::tearDown();
    }

    public function test_lists_by_credit_record_ids_returns_flows_ordered_by_record_and_id(): void
    {
        DB::connection(DB_API)->table('user_credit_ledger')->insert([
            $this->ledger(id: 1, recordId: 2, type: TypeCreditLedger::Grant, ticket: 'T-GRANT-2'),
            $this->ledger(id: 2, recordId: 1, type: TypeCreditLedger::Unlock, amount: 30),
            $this->ledger(id: 3, recordId: 1, type: TypeCreditLedger::Grant, ticket: 'T-GRANT-1'),
            $this->ledger(id: 4, recordId: 3, type: TypeCreditLedger::ExpireDeduct, amount: 10),
        ]);

        $ledgers = (new UserCreditLedgerRepository(new UserCreditLedger))
            ->listsByCreditRecordIds([1, 2]);

        $this->assertSame(
            [2, 3, 1],
            array_map(fn (UserCreditLedger $ledger): int => (int) $ledger->id, $ledgers)
        );
        $this->assertSame(TypeCreditLedger::Grant, $ledgers[1]->type);
        $this->assertSame('T-GRANT-1', $ledgers[1]->ticket);
    }

    public function test_lists_by_credit_record_ids_returns_empty_array_without_ids(): void
    {
        $ledgers = (new UserCreditLedgerRepository(new UserCreditLedger))
            ->listsByCreditRecordIds([]);

        $this->assertSame([], $ledgers);
    }

    public function test_paginate_by_user_code_joins_credit_type_and_applies_filters(): void
    {
        Schema::connection(DB_API)->create('user_credit_record', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_code');
            $table->unsignedTinyInteger('credit_type')->default(0);
            $table->timestamps();
        });

        DB::connection(DB_API)->table('user_credit_record')->insert([
            ['id' => 1, 'user_code' => 10001, 'credit_type' => 1, 'created_at' => '2026-08-20 10:00:00', 'updated_at' => '2026-08-20 10:00:00'],
            ['id' => 2, 'user_code' => 10001, 'credit_type' => 0, 'created_at' => '2026-08-20 10:00:00', 'updated_at' => '2026-08-20 10:00:00'],
        ]);
        DB::connection(DB_API)->table('user_credit_ledger')->insert([
            $this->ledger(id: 1, recordId: 1, type: TypeCreditLedger::Grant, ticket: 'T-1'),
            $this->ledger(id: 2, recordId: 2, type: TypeCreditLedger::Unlock, amount: 30, ticket: 'T-2'),
        ]);

        $repository = new UserCreditLedgerRepository(new UserCreditLedger);

        [$count, $records] = $repository->paginateByUserCode(10001, ['credit_type' => 1], 20, 0);

        $this->assertSame(1, $count);
        $this->assertSame([1], array_map(fn (UserCreditLedger $ledger): int => (int) $ledger->id, $records));
        $this->assertSame(1, (int) $records[0]->credit_type);
        $this->assertSame('T-1', $records[0]->ticket);
    }

    private function ledger(
        int $id,
        int $recordId,
        TypeCreditLedger $type,
        float $amount = 100,
        ?string $ticket = null,
    ): array {
        return [
            'id' => $id,
            'user_credit_record_id' => $recordId,
            'user_code' => 10000 + $recordId,
            'trade_account_id' => 90000 + $recordId,
            'type' => $type->value,
            'amount' => $amount,
            'ticket' => $ticket,
            'created_at' => '2026-09-01 10:00:00',
            'updated_at' => '2026-09-01 10:00:00',
        ];
    }
}
