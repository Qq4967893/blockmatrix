<?php

namespace Tests\Unit\Repositories\Api;

use App\Models\Api\UserCreditRecord;
use App\Repositories\Api\UserCreditRecordRepository;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Scaffold\Framework\Enums\TypeCoupon;
use Scaffold\Framework\Enums\TypeCreditLedger;
use Tests\TestCase;

class UserCreditRecordRepositoryTest extends TestCase
{
    private array $originalApiConnection;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-01 12:00:00');

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
            $table->unsignedBigInteger('trade_account_id');
            $table->decimal('grant_amount', 18, 6);
            $table->decimal('unlocked_amount', 18, 6)->default(0);
            $table->string('biz_type');
            $table->unsignedBigInteger('biz_id');
            $table->timestamp('end_at')->nullable();
            $table->timestamps();
        });

        Schema::connection(DB_API)->create('user_coupon', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('coupon_type');
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        Schema::connection(DB_API)->drop('user_credit_record');
        Schema::connection(DB_API)->drop('user_coupon');
        Schema::connection(DB_API)->drop('user_credit_ledger');
        DB::purge(DB_API);
        config(['database.connections.'.DB_API => $this->originalApiConnection]);

        parent::tearDown();
    }

    public function test_latest_by_user_code_returns_the_latest_credit_record_regardless_of_biz_type(): void
    {
        DB::connection(DB_API)->table('user_credit_record')->insert([
            $this->record(id: 1, userCode: 10001, bizType: 'coupon'),
            $this->record(id: 2, userCode: 10002, bizType: 'coupon'),
            $this->record(id: 3, userCode: 10001, bizType: 'manual'),
        ]);

        $record = (new UserCreditRecordRepository(new UserCreditRecord))->latestByUserCode(10001);

        $this->assertSame(3, (int) $record->id);
        $this->assertSame('manual', $record->biz_type->value);
    }

    public function test_summarize_by_user_code_separates_history_from_current_records(): void
    {
        DB::connection(DB_API)->table('user_credit_record')->insert([
            $this->record(id: 1, userCode: 10001),
            $this->record(id: 2, userCode: 10001, grantAmount: 50, expiresAt: null),
            $this->record(id: 3, userCode: 10001, grantAmount: 30, expiresAt: '2026-08-31 12:00:00'),
            $this->record(id: 4, userCode: 10002, grantAmount: 999),
        ]);
        DB::connection(DB_API)->table('user_credit_ledger')->insert([
            $this->unlockLedger(recordId: 1, amount: 25),
            $this->unlockLedger(recordId: 1, amount: 15),
            $this->unlockLedger(recordId: 2, amount: 10),
            $this->unlockLedger(recordId: 3, amount: 5),
            $this->unlockLedger(recordId: 4, amount: 999),
        ]);

        $summary = (new UserCreditRecordRepository(new UserCreditRecord))->summarizeByUserCode(10001);

        $this->assertSame([
            'total_bonus_amount' => 180.0,
            'historical_unlocked_amount' => 55.0,
            'current_bonus_amount' => 150.0,
            'current_unlocked_amount' => 50.0,
        ], $summary);
    }

    public function test_get_credit_bonus_detail_list_joins_user_coupon_and_orders_by_id_desc(): void
    {
        $this->insertCoupon(346, TypeCoupon::CreditBonus->value);
        DB::connection(DB_API)->table('user_credit_record')->insert([
            $this->record(id: 11, userCode: 10001, bizId: 346),
            $this->record(id: 12, userCode: 10002, bizId: 346),
            $this->record(id: 13, userCode: 10003, bizId: 346, bizType: 'manual'),
            $this->record(id: 14, userCode: 10004, bizId: 999),
        ]);

        [$count, $records] = (new UserCreditRecordRepository(new UserCreditRecord))
            ->getCreditBonusDetailList(346, 20, 0);

        $this->assertSame(2, $count);
        $this->assertSame([12, 11], array_map(fn ($record): int => (int) $record->id, $records));
    }

    public function test_get_credit_bonus_detail_list_accepts_credit_bonus_group_types_and_paginates(): void
    {
        $this->insertCoupon(347, TypeCoupon::RestrictedCreditBonus->value);
        DB::connection(DB_API)->table('user_credit_record')->insert([
            $this->record(id: 21, userCode: 10001, bizId: 347),
            $this->record(id: 22, userCode: 10002, bizId: 347),
            $this->record(id: 23, userCode: 10003, bizId: 347),
        ]);

        [$count, $records] = (new UserCreditRecordRepository(new UserCreditRecord))
            ->getCreditBonusDetailList(347, 2, 1);

        $this->assertSame(3, $count);
        $this->assertSame([22, 21], array_map(fn ($record): int => (int) $record->id, $records));
    }

    private function unlockLedger(int $recordId, float $amount): array
    {
        return [
            'user_credit_record_id' => $recordId,
            'user_code' => 10001,
            'trade_account_id' => 90000 + $recordId,
            'type' => TypeCreditLedger::Unlock->value,
            'amount' => $amount,
            'ticket' => null,
            'created_at' => '2026-08-20 10:00:00',
            'updated_at' => '2026-08-20 10:00:00',
        ];
    }

    private function insertCoupon(int $id, int $couponType): void
    {
        DB::connection(DB_API)->table('user_coupon')->insert([
            'id' => $id,
            'coupon_type' => $couponType,
        ]);
    }

    private function record(
        int $id,
        int $userCode,
        string $bizType = 'coupon',
        float $grantAmount = 100,
        float $unlockedAmount = 25,
        ?string $expiresAt = '2026-09-15 12:00:00',
        ?int $bizId = null,
    ): array {
        return [
            'id' => $id,
            'user_code' => $userCode,
            'trade_account_id' => 90000 + $id,
            'grant_amount' => $grantAmount,
            'unlocked_amount' => $unlockedAmount,
            'biz_type' => $bizType,
            'biz_id' => $bizId ?? $id,
            'end_at' => $expiresAt,
            'created_at' => '2026-08-20 10:00:00',
            'updated_at' => '2026-08-20 10:00:00',
        ];
    }
}
