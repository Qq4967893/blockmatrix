<?php

namespace Tests\Unit\Services\Api;

use App\Repositories\Api\UserCreditLedgerRepository;
use App\Repositories\Api\UserCreditRecordRepository;
use App\Services\Api\CreditFlowService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Scaffold\Framework\Enums\TypeCreditLedger;
use Scaffold\Framework\Models\Api\UserCreditLedger;
use Scaffold\Framework\Models\Api\UserCreditRecord;
use Tests\TestCase;

class CreditFlowServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private array $originalApiConnection;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('zh_CN');
        $this->originalApiConnection = config('database.connections.'.DB_API);
        config([
            'database.connections.'.DB_API => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
        DB::purge(DB_API);

        Schema::connection(DB_API)->create('user_credit_record', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_code');
            $table->unsignedTinyInteger('credit_type')->default(0);
            $table->timestamps();
        });

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
        Schema::connection(DB_API)->drop('user_credit_record');
        DB::purge(DB_API);
        config(['database.connections.'.DB_API => $this->originalApiConnection]);

        parent::tearDown();
    }

    public function test_credit_flow_list_returns_ledger_types_with_credit_directions(): void
    {
        DB::connection(DB_API)->table('user_credit_record')->insert([
            $this->creditRecord(id: 1, userCode: 10001, creditType: UserCreditRecord::RESTRICTED),
            $this->creditRecord(id: 2, userCode: 10001, creditType: UserCreditRecord::RESTRICTED),
            $this->creditRecord(id: 3, userCode: 10001, creditType: UserCreditRecord::GENERAL),
        ]);
        DB::connection(DB_API)->table('user_credit_ledger')->insert([
            $this->flow(id: 1, userCode: 10001, type: TypeCreditLedger::Grant, amount: 100, ticket: 'T-1', createdAt: '2026-09-01 10:00:00', balanceAfter: 100),
            $this->flow(id: 2, userCode: 10001, type: TypeCreditLedger::Unlock, amount: 30, ticket: 'T-2', createdAt: '2026-09-01 11:00:00', balanceAfter: 70),
            $this->flow(id: 3, userCode: 10001, type: TypeCreditLedger::ExpireDeduct, amount: 70, ticket: null, createdAt: '2026-09-01 12:00:00', balanceAfter: 0),
            $this->flow(id: 4, userCode: 10002, type: TypeCreditLedger::Grant, amount: 500, ticket: 'T-5', createdAt: '2026-09-01 15:00:00'),
        ]);

        $service = $this->makeService();

        $result = $service->getList(10001, [], 2, 1);

        $this->assertSame(3, $result['total_count']);
        $this->assertSame([
            [
                'id' => 2,
                'trade_ticket' => 'T-2',
                'fund_type' => TypeCreditLedger::Unlock->value,
                'fund_type_text' => '赠金解锁',
                'credit_type' => UserCreditRecord::RESTRICTED,
                'credit_type_text' => '限制型赠金',
                'created_at' => '2026-09-01 11:00:00',
                'amount' => -30.0,
                'after_balance' => 70.0,
            ],
            [
                'id' => 1,
                'trade_ticket' => 'T-1',
                'fund_type' => TypeCreditLedger::Grant->value,
                'fund_type_text' => '赠金发放',
                'credit_type' => UserCreditRecord::RESTRICTED,
                'credit_type_text' => '限制型赠金',
                'created_at' => '2026-09-01 10:00:00',
                'amount' => 100.0,
                'after_balance' => 100.0,
            ],
        ], $result['data']);
    }

    public function test_credit_flow_list_filters_by_credit_type(): void
    {
        DB::connection(DB_API)->table('user_credit_record')->insert([
            $this->creditRecord(id: 1, userCode: 10001, creditType: UserCreditRecord::RESTRICTED),
            $this->creditRecord(id: 2, userCode: 10001, creditType: UserCreditRecord::GENERAL),
        ]);
        DB::connection(DB_API)->table('user_credit_ledger')->insert([
            $this->flow(id: 1, userCode: 10001, type: TypeCreditLedger::Grant, amount: 100, ticket: 'T-1', createdAt: '2026-09-01 10:00:00'),
            $this->flow(id: 2, userCode: 10001, type: TypeCreditLedger::Unlock, amount: 30, ticket: 'T-2', createdAt: '2026-09-01 11:00:00'),
        ]);

        $result = $this->makeService()->getList(10001, ['credit_type' => UserCreditRecord::GENERAL], 20, 0);

        $this->assertSame(1, $result['total_count']);
        $this->assertSame(2, $result['data'][0]['id']);
        $this->assertSame(UserCreditRecord::GENERAL, $result['data'][0]['credit_type']);
        $this->assertSame('通用赠金', $result['data'][0]['credit_type_text']);
    }

    public function test_credit_flow_list_marks_deduct_types_negative(): void
    {
        DB::connection(DB_API)->table('user_credit_ledger')->insert([
            $this->flow(id: 1, userCode: 10001, type: TypeCreditLedger::ExpireDeduct, amount: 70, ticket: null, createdAt: '2026-09-01 10:00:00'),
            $this->flow(id: 2, userCode: 10001, type: TypeCreditLedger::ManualClear, amount: 8, ticket: 'T-2', createdAt: '2026-09-01 11:00:00'),
            $this->flow(id: 3, userCode: 10001, type: TypeCreditLedger::TradeLoss, amount: 13, ticket: 'T-3', createdAt: '2026-09-01 12:00:00'),
        ]);

        $result = $this->makeService()->getList(10001, [], 20, 0);

        $this->assertSame(70.0, abs($result['data'][2]['amount']));
        $this->assertSame(-70.0, $result['data'][2]['amount']);
        $this->assertSame(-8.0, $result['data'][1]['amount']);
        $this->assertSame(-13.0, $result['data'][0]['amount']);
        $this->assertSame('', $result['data'][2]['trade_ticket']);
        $this->assertSame(0.0, $result['data'][2]['after_balance']);
        $this->assertSame(0.0, $result['data'][1]['after_balance']);
    }

    public function test_credit_flow_list_returns_empty_when_user_has_no_ledger(): void
    {
        $this->assertSame(
            ['data' => [], 'total_count' => 0],
            $this->makeService()->getList(10001, [], 20, 0)
        );
    }

    public function test_credit_flow_list_applies_ticket_time_and_fund_type_filters(): void
    {
        DB::connection(DB_API)->table('user_credit_ledger')->insert([
            $this->flow(id: 1, userCode: 10001, type: TypeCreditLedger::Grant, amount: 100, ticket: 'T-1', createdAt: '2026-09-01 10:00:00'),
            $this->flow(id: 2, userCode: 10001, type: TypeCreditLedger::Unlock, amount: 30, ticket: 'T-2', createdAt: '2026-09-01 11:00:00'),
            $this->flow(id: 3, userCode: 10001, type: TypeCreditLedger::Unlock, amount: 20, ticket: 'T-3', createdAt: '2026-09-02 11:00:00'),
        ]);

        $result = $this->makeService()->getList(10001, [
            'trade_ticket' => 'T-2',
            'created_start' => '2026-09-01 00:00:00',
            'created_end' => '2026-09-01 23:59:59',
            'fund_type' => TypeCreditLedger::Unlock->value,
        ], 20, 0);

        $this->assertSame(1, $result['total_count']);
        $this->assertSame(2, $result['data'][0]['id']);
    }

    public function test_credit_flow_options_returns_all_ledger_types_and_summary(): void
    {
        $summary = [
            'total_bonus_amount' => 180.0,
            'historical_unlocked_amount' => 40.0,
            'current_bonus_amount' => 150.0,
            'current_unlocked_amount' => 35.0,
        ];
        $userCreditRecordRepo = Mockery::mock(UserCreditRecordRepository::class);
        $userCreditRecordRepo->shouldReceive('summarizeByUserCode')
            ->once()
            ->with(10001)
            ->andReturn($summary);

        $service = new CreditFlowService(
            new UserCreditLedgerRepository(new UserCreditLedger),
            $userCreditRecordRepo,
        );

        $this->assertSame([
            'fund_type_options' => [
                ['code' => TypeCreditLedger::Grant->value, 'label' => '赠金发放'],
                ['code' => TypeCreditLedger::ExpireDeduct->value, 'label' => '赠金过期扣除'],
                ['code' => TypeCreditLedger::ManualClear->value, 'label' => '赠金清除'],
                ['code' => TypeCreditLedger::TradeLoss->value, 'label' => '交易亏损扣除'],
                ['code' => TypeCreditLedger::Unlock->value, 'label' => '赠金解锁'],
            ],
            'credit_type_options' => [
                ['code' => UserCreditRecord::GENERAL, 'label' => '通用赠金'],
                ['code' => UserCreditRecord::RESTRICTED, 'label' => '限制型赠金'],
            ],
            'summary' => $summary,
        ], $service->options(10001));
    }

    private function makeService(): CreditFlowService
    {
        return new CreditFlowService(
            new UserCreditLedgerRepository(new UserCreditLedger),
            Mockery::mock(UserCreditRecordRepository::class),
        );
    }

    private function creditRecord(int $id, int $userCode, int $creditType): array
    {
        return [
            'id' => $id,
            'user_code' => $userCode,
            'credit_type' => $creditType,
            'created_at' => '2026-08-20 10:00:00',
            'updated_at' => '2026-08-20 10:00:00',
        ];
    }

    private function flow(
        int $id,
        int $userCode,
        TypeCreditLedger $type,
        float $amount,
        ?string $ticket,
        string $createdAt,
        ?float $balanceAfter = null,
    ): array {
        return [
            'id' => $id,
            'user_credit_record_id' => $id,
            'user_code' => $userCode,
            'trade_account_id' => 90001,
            'type' => $type->value,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'ticket' => $ticket,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}
