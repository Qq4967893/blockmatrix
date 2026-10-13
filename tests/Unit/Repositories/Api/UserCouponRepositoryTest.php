<?php

namespace Tests\Unit\Repositories\Api;

use App\Models\Api\UserCoupon;
use App\Repositories\Api\UserCouponRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Scaffold\Framework\Enums\TypeCoupon;
use Tests\TestCase;

class UserCouponRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.api', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::connection(DB_API)->getPdo()->sqliteCreateFunction('JSON_UNQUOTE', static function ($value): ?string {
            return $value === null ? null : (string) $value;
        }, 1);
        Schema::connection(DB_API)->create('user_coupon', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('coupon_type')->default(0);
            $table->unsignedInteger('coupon_id');
            $table->unsignedInteger('status');
            $table->unsignedInteger('user_code')->default(0);
            $table->decimal('total_amount', 18, 6)->default(0);
            $table->text('coupon_config')->nullable();
            $table->text('event_data')->nullable();
            $table->timestamp('triggered_at')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->decimal('used_amount', 18, 6)->default(0);
            $table->timestamps();
        });
        Schema::connection(DB_API)->create('user_coupon_used_record', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('user_coupon_id');
            $table->string('biz_id', 255)->nullable();
            $table->text('event_data')->nullable();
            $table->unsignedInteger('trade_account_id')->nullable();
            $table->unsignedInteger('user_code')->nullable();
            $table->timestamps();
        });
        Schema::connection(DB_API)->create('coupon', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name_internal')->nullable();
            $table->text('name_external')->nullable();
        });
        Schema::connection(DB_API)->create('trade_account', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('trade_account_id');
            $table->unsignedInteger('user_code');
            $table->unsignedInteger('trade_account_type')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection(DB_API)->drop('trade_account');
        Schema::connection(DB_API)->drop('coupon');
        Schema::connection(DB_API)->drop('user_coupon_used_record');
        Schema::connection(DB_API)->drop('user_coupon');
        DB::disconnect(DB_API);

        parent::tearDown();
    }

    public function test_force_void_only_targets_voidable_user_coupon_statuses(): void
    {
        DB::connection(DB_API)->table('user_coupon')->insert([
            ['coupon_id' => 10, 'status' => UserCoupon::STATUS_UNCLAIMED, 'used_amount' => 0],
            ['coupon_id' => 10, 'status' => UserCoupon::STATUS_INACTIVE, 'used_amount' => 0],
            ['coupon_id' => 10, 'status' => UserCoupon::STATUS_USABLE, 'used_amount' => 0],
            ['coupon_id' => 10, 'status' => UserCoupon::STATUS_USABLE, 'used_amount' => 1],
            ['coupon_id' => 10, 'status' => UserCoupon::STATUS_UNUSABLE, 'used_amount' => 0],
            ['coupon_id' => 10, 'status' => UserCoupon::STATUS_EXPIRED, 'used_amount' => 0],
            ['coupon_id' => 10, 'status' => UserCoupon::STATUS_VOIDED, 'used_amount' => 0],
            ['coupon_id' => 11, 'status' => UserCoupon::STATUS_USABLE, 'used_amount' => 0],
        ]);

        $affected = (new UserCouponRepository(new UserCoupon))->forceVoidByCouponId(10);
        $statuses = DB::connection(DB_API)
            ->table('user_coupon')
            ->where('coupon_id', 10)
            ->orderBy('id')
            ->pluck('status')
            ->all();

        $this->assertSame(4, $affected);
        $this->assertSame([
            UserCoupon::STATUS_VOIDED,
            UserCoupon::STATUS_VOIDED,
            UserCoupon::STATUS_VOIDED,
            UserCoupon::STATUS_VOIDED,
            UserCoupon::STATUS_UNUSABLE,
            UserCoupon::STATUS_EXPIRED,
            UserCoupon::STATUS_VOIDED,
        ], $statuses);
        $this->assertSame(
            UserCoupon::STATUS_USABLE,
            DB::connection(DB_API)->table('user_coupon')->where('coupon_id', 11)->value('status')
        );
    }

    public function test_trade_detail_list_includes_fee_reduction_coupons(): void
    {
        DB::connection(DB_API)->table('user_coupon')->insert([
            ['coupon_type' => TypeCoupon::TradeCashback->value, 'coupon_id' => 20, 'status' => UserCoupon::STATUS_USABLE, 'used_amount' => 0],
            ['coupon_type' => TypeCoupon::FeeReduction->value, 'coupon_id' => 21, 'status' => UserCoupon::STATUS_USABLE, 'used_amount' => 0],
            ['coupon_type' => TypeCoupon::CreditBonus->value, 'coupon_id' => 22, 'status' => UserCoupon::STATUS_USABLE, 'used_amount' => 0],
        ]);
        DB::connection(DB_API)->table('user_coupon_used_record')->insert([
            ['user_coupon_id' => 1, 'biz_id' => 'T1001', 'event_data' => '{"ticket":"T1001"}'],
            ['user_coupon_id' => 2, 'biz_id' => 'T2002', 'event_data' => '{"ticket":"T2002"}'],
            ['user_coupon_id' => 3, 'biz_id' => 'T3003', 'event_data' => '{"ticket":"T3003"}'],
        ]);

        $repository = new UserCouponRepository(new UserCoupon);

        [$tradeCashbackRecords, $tradeCashbackCount] = $repository->getTradeCashbackDetailList(1, [], 20, 0);
        [$feeReductionRecords, $feeReductionCount] = $repository->getTradeCashbackDetailList(2, [], 20, 0);
        [$creditBonusRecords, $creditBonusCount] = $repository->getTradeCashbackDetailList(3, [], 20, 0);

        $this->assertSame(1, $tradeCashbackCount);
        $this->assertSame('T1001', $tradeCashbackRecords[0]->biz_id);
        $this->assertSame(1, $feeReductionCount);
        $this->assertSame('T2002', $feeReductionRecords[0]->biz_id);
        $this->assertSame([], $creditBonusRecords);
        $this->assertSame(0, $creditBonusCount);
    }

    public function test_redeem_status_filter_treats_fee_reduction_like_trade_cashback(): void
    {
        DB::connection(DB_API)->table('user_coupon')->insert([
            ['coupon_type' => TypeCoupon::FeeReduction->value, 'coupon_id' => 30, 'status' => UserCoupon::STATUS_USABLE, 'used_amount' => 50, 'total_amount' => 100],
            ['coupon_type' => TypeCoupon::FeeReduction->value, 'coupon_id' => 31, 'status' => UserCoupon::STATUS_USABLE, 'used_amount' => 100, 'total_amount' => 100],
            ['coupon_type' => TypeCoupon::TradeCashback->value, 'coupon_id' => 32, 'status' => UserCoupon::STATUS_USABLE, 'used_amount' => 50, 'total_amount' => 100],
            ['coupon_type' => TypeCoupon::LossRecover->value, 'coupon_id' => 33, 'status' => UserCoupon::STATUS_USABLE, 'used_amount' => 1, 'total_amount' => 100],
        ]);

        $repository = new UserCouponRepository(new UserCoupon);

        [$partialRecords, $partialCount] = $repository->getList(['redeem_status' => 1], 20, 0);
        [$fullRecords, $fullCount] = $repository->getList(['redeem_status' => 2], 20, 0);

        $this->assertSame(2, $partialCount);
        $this->assertSame(
            [TypeCoupon::TradeCashback->value, TypeCoupon::FeeReduction->value],
            array_map(static fn ($record): int => (int) $record->coupon_type, $partialRecords)
        );

        $this->assertSame(2, $fullCount);
        $this->assertSame(
            [TypeCoupon::LossRecover->value, TypeCoupon::FeeReduction->value],
            array_map(static fn ($record): int => (int) $record->coupon_type, $fullRecords)
        );
    }
}
