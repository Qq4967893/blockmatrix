<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\UserCreditRecord;
use App\Repositories\Api\CouponRepository;
use App\Repositories\Api\GrantRuleRepository;
use App\Repositories\Api\SourceRepository;
use App\Repositories\Api\UserCouponRepository;
use App\Repositories\Api\UserCreditLedgerRepository;
use App\Repositories\Api\UserCreditRecordRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Repositories\Et6\TradeRepository;
use App\Services\Api\CouponService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Scaffold\Framework\Enums\CreditBizType;
use Scaffold\Framework\Enums\TypeCoupon;
use Scaffold\Framework\Enums\TypeCreditLedger;
use Scaffold\Framework\Models\Api\UserCreditLedger;
use Tests\TestCase;

class CouponServiceCreditBonusDetailsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private array $originalApiConnection;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-01 12:00:00');
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

        Schema::connection(DB_API)->create('user_coupon', function (Blueprint $table): void {
            $table->id();
            $table->unsignedTinyInteger('coupon_type');
        });
        Schema::connection(DB_API)->create('user_credit_record', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_code');
            $table->unsignedBigInteger('trade_account_id');
            $table->decimal('grant_amount', 18, 6);
            $table->decimal('deduct_amount', 18, 6)->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->string('biz_type');
            $table->unsignedBigInteger('biz_id');
            $table->timestamp('end_at')->nullable();
            $table->timestamps();
        });
        Schema::connection(DB_API)->create('user_credit_ledger', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_credit_record_id');
            $table->unsignedBigInteger('user_code');
            $table->unsignedBigInteger('trade_account_id');
            $table->unsignedTinyInteger('type');
            $table->decimal('amount', 18, 6)->default(0);
            $table->string('ticket')->nullable();
            $table->timestamps();
        });

        DB::connection(DB_API)->table('user_coupon')->insert([
            ['id' => 17, 'coupon_type' => TypeCoupon::CreditBonus->value],
            ['id' => 18, 'coupon_type' => TypeCoupon::TradeCashback->value],
        ]);
        DB::connection(DB_API)->table('user_credit_record')->insert([
            $this->creditRecord(id: 1, tradeAccountId: 9001, grantAmount: 100, deductAmount: 40),
            $this->creditRecord(id: 2, tradeAccountId: 9002, grantAmount: 200, deductAmount: 50),
            $this->creditRecord(id: 3, tradeAccountId: 9003, grantAmount: 300, status: 0, expiresAt: '2026-08-31 12:00:00'),
            $this->creditRecord(id: 4, tradeAccountId: 9004, grantAmount: 100, deductAmount: 100, status: 0),
            $this->creditRecord(id: 5, tradeAccountId: 9005, grantAmount: 500, userCouponId: 18),
            $this->creditRecord(id: 6, tradeAccountId: 9006, grantAmount: 600, bizType: CreditBizType::Manual),
        ]);
        DB::connection(DB_API)->table('user_credit_ledger')->insert([
            $this->ledger(recordId: 1, type: TypeCreditLedger::Grant, amount: 100, ticket: 'T-GRANT-1'),
            $this->ledger(recordId: 1, type: TypeCreditLedger::Unlock, amount: 25),
            $this->ledger(recordId: 1, type: TypeCreditLedger::TradeLoss, amount: 15),
            $this->ledger(recordId: 2, type: TypeCreditLedger::Grant, amount: 200, ticket: 'T-GRANT-2'),
            $this->ledger(recordId: 2, type: TypeCreditLedger::Unlock, amount: 50),
            $this->ledger(recordId: 4, type: TypeCreditLedger::Grant, amount: 100, ticket: 'T-GRANT-4'),
            $this->ledger(recordId: 4, type: TypeCreditLedger::ExpireDeduct, amount: 100),
        ]);
    }

    protected function tearDown(): void
    {
        Schema::connection(DB_API)->drop('user_credit_record');
        Schema::connection(DB_API)->drop('user_coupon');
        Schema::connection(DB_API)->drop('user_credit_ledger');
        DB::purge(DB_API);
        config(['database.connections.'.DB_API => $this->originalApiConnection]);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_credit_bonus_details_maps_ledger_fields_and_record_status(): void
    {
        $result = $this->service()->getCreditBonusDetailList(17, 3, 1);

        $this->assertSame(4, $result['total_count']);
        $this->assertSame([
            [
                'id' => 3,
                'ticket' => '',
                'initial_bonus_amount' => '300.000000',
                'remaining_bonus_amount' => '300.000000',
                'unlocked_bonus_amount' => '0.000000',
                'currency' => 'USDT',
                'bonus_status' => 0,
                'bonus_status_text' => '不可用',
                'credited_at' => '2026-08-20 10:00:00',
                'expired_at' => '2026-08-31 12:00:00',
            ],
            [
                'id' => 2,
                'ticket' => 'T-GRANT-2',
                'initial_bonus_amount' => '200.000000',
                'remaining_bonus_amount' => '150.000000',
                'unlocked_bonus_amount' => '50.000000',
                'currency' => 'USDT',
                'bonus_status' => 1,
                'bonus_status_text' => '可用',
                'credited_at' => '2026-08-20 10:00:00',
                'expired_at' => '2026-09-15 12:00:00',
            ],
            [
                'id' => 1,
                'ticket' => 'T-GRANT-1',
                'initial_bonus_amount' => '100.000000',
                'remaining_bonus_amount' => '60.000000',
                'unlocked_bonus_amount' => '25.000000',
                'currency' => 'USDT',
                'bonus_status' => 1,
                'bonus_status_text' => '可用',
                'credited_at' => '2026-08-20 10:00:00',
                'expired_at' => '2026-09-15 12:00:00',
            ],
        ], $result['data']);
    }

    public function test_credit_bonus_details_returns_empty_result_for_unknown_user_coupon(): void
    {
        $result = $this->service()->getCreditBonusDetailList(19, 20, 0);

        $this->assertSame(['data' => [], 'total_count' => 0], $result);
    }

    private function service(): CouponService
    {
        return new CouponService(
            Mockery::mock(CouponRepository::class),
            Mockery::mock(GrantRuleRepository::class),
            Mockery::mock(UserCouponRepository::class),
            Mockery::mock(TradeRepository::class),
            Mockery::mock(SourceRepository::class),
            Mockery::mock(UserOperationLogRepository::class),
            new UserCreditRecordRepository(new UserCreditRecord),
            new UserCreditLedgerRepository(new UserCreditLedger),
        );
    }

    private function creditRecord(
        int $id,
        int $tradeAccountId,
        float $grantAmount,
        float $deductAmount = 0,
        int $status = 1,
        string $expiresAt = '2026-09-15 12:00:00',
        int $userCouponId = 17,
        ?CreditBizType $bizType = null,
    ): array {
        return [
            'id' => $id,
            'user_code' => 10001,
            'trade_account_id' => $tradeAccountId,
            'grant_amount' => $grantAmount,
            'deduct_amount' => $deductAmount,
            'status' => $status,
            'biz_type' => ($bizType ?? CreditBizType::Coupon)->value,
            'biz_id' => $userCouponId,
            'end_at' => $expiresAt,
            'created_at' => '2026-08-20 10:00:00',
            'updated_at' => '2026-08-20 10:00:00',
        ];
    }

    private function ledger(int $recordId, TypeCreditLedger $type, float $amount, ?string $ticket = null): array
    {
        return [
            'user_credit_record_id' => $recordId,
            'user_code' => 10001,
            'trade_account_id' => 9000 + $recordId,
            'type' => $type->value,
            'amount' => $amount,
            'ticket' => $ticket,
            'created_at' => '2026-08-20 10:00:00',
            'updated_at' => '2026-08-20 10:00:00',
        ];
    }
}
