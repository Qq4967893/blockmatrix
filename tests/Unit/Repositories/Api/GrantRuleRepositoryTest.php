<?php

namespace Tests\Unit\Repositories\Api;

use App\Models\Api\Coupon;
use App\Models\Api\GrantRule;
use App\Models\Api\UserCoupon;
use App\Repositories\Api\GrantRuleRepository;
use App\Repositories\Api\UserCouponRepository;
use Scaffold\Framework\Enums\CreditBizType;
use Scaffold\Framework\Enums\TypeCoupon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GrantRuleRepositoryTest extends TestCase
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

        $this->createTables();
    }

    protected function tearDown(): void
    {
        DB::purge(DB_API);
        config(['database.connections.'.DB_API => $this->originalApiConnection]);

        parent::tearDown();
    }

    public function test_grant_rule_create_and_queries_use_target_field(): void
    {
        $repository = new GrantRuleRepository(new GrantRule);

        $grantRule = $repository->createGrantRule([
            'name' => 'Created coupon grant rule',
            'target_type' => GrantRule::TARGET_COUPON,
            'target' => 100,
            'event_key' => 'manual',
            'event_params' => ['user_codes' => [10001]],
            'total_count' => 20,
            'start_at' => now()->subDay()->format('Y-m-d H:i:s'),
            'end_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'status' => GrantRule::STATUS_ENABLED,
        ]);

        $this->assertSame(100, $grantRule->target);
        $this->assertSame(
            100,
            DB::connection(DB_API)->table('grant_rule')->value('target')
        );
        $this->assertSame(
            ['Coupon grant rule', 'Created coupon grant rule'],
            $repository->getEnabledUnfinishedNamesByCouponId(100)
        );

        [$rows, $count] = $repository->getList(['target' => 100], 20, 0);

        $this->assertSame(2, $count);
        $this->assertCount(2, $rows);
        $this->assertSame(100, (int) $rows[0]->target);
        $this->assertSame('Test coupon', $rows[0]->coupon_name);
        $this->assertSame(20, (int) $rows[0]->total_count);
        $this->assertSame(0, (int) $rows[0]->granted_count);
    }

    public function test_grant_record_list_filters_target(): void
    {
        DB::connection(DB_API)->table('user_coupon')->insert([
            'rule_id' => 10,
            'coupon_id' => 100,
            'user_code' => 10001,
            'status' => UserCoupon::STATUS_USABLE,
        ]);

        [$rows, $count] = (new UserCouponRepository(new UserCoupon))
            ->getGrantRecordList(['target' => 100], 20, 0);

        $this->assertSame(1, $count);
        $this->assertCount(1, $rows);
        $this->assertSame(100, (int) $rows[0]->target);
        $this->assertSame('Coupon grant rule', $rows[0]->rule_name);
    }

    public function test_grant_record_list_coupon_type_group_matches_credit_bonus_types(): void
    {
        DB::connection(DB_API)->table('coupon')->insert([
            ['id' => 101, 'name_internal' => 'General bonus', 'coupon_type' => TypeCoupon::CreditBonus->value],
            ['id' => 102, 'name_internal' => 'Restricted bonus', 'coupon_type' => TypeCoupon::RestrictedCreditBonus->value],
        ]);
        DB::connection(DB_API)->table('user_coupon')->insert([
            ['rule_id' => 10, 'coupon_id' => 100, 'user_code' => 10001, 'coupon_type' => 1, 'status' => UserCoupon::STATUS_USABLE],
            ['rule_id' => 10, 'coupon_id' => 101, 'user_code' => 10002, 'coupon_type' => TypeCoupon::CreditBonus->value, 'status' => UserCoupon::STATUS_USABLE],
            ['rule_id' => 10, 'coupon_id' => 102, 'user_code' => 10003, 'coupon_type' => TypeCoupon::RestrictedCreditBonus->value, 'status' => UserCoupon::STATUS_USABLE],
        ]);

        $repository = new UserCouponRepository(new UserCoupon);

        [$rows, $count] = $repository->getGrantRecordList(
            ['coupon_type' => Coupon::COUPON_TYPE_CREDIT_BONUS_GROUP],
            20,
            0
        );
        $userCodes = array_map(static fn (mixed $row): int => (int) $row->user_code, $rows);

        $this->assertSame(2, $count);
        $this->assertSame([10003, 10002], $userCodes);

        [$rows, $count] = $repository->getGrantRecordList(
            ['coupon_type' => TypeCoupon::RestrictedCreditBonus->value],
            20,
            0
        );

        $this->assertSame(1, $count);
        $this->assertSame(10003, (int) $rows[0]->user_code);
    }

    public function test_apply_coupon_type_filter_maps_group_code_to_where_in(): void
    {
        $groupQuery = Coupon::query();
        Coupon::applyCouponTypeFilter($groupQuery, 'coupon_type', Coupon::COUPON_TYPE_CREDIT_BONUS_GROUP);

        $this->assertSame('select * from "coupon" where "coupon_type" in (?, ?)', $groupQuery->toSql());
        $this->assertSame(
            [TypeCoupon::CreditBonus->value, TypeCoupon::RestrictedCreditBonus->value],
            array_map('intval', $groupQuery->getBindings())
        );

        $singleQuery = Coupon::query();
        Coupon::applyCouponTypeFilter($singleQuery, 'coupon_type', TypeCoupon::LossRecover->value);

        $this->assertSame(
            'select * from "coupon" where "coupon_type" = ?',
            $singleQuery->toSql()
        );
        $this->assertSame([TypeCoupon::LossRecover->value], array_map('intval', $singleQuery->getBindings()));
    }

    private function createTables(): void
    {
        Schema::connection(DB_API)->create('grant_rule', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
            $table->unsignedTinyInteger('target_type')->default(0);
            $table->unsignedBigInteger('target')->default(0);
            $table->string('event_key')->nullable();
            $table->text('event_params')->nullable();
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('granted_count')->default(0);
            $table->unsignedTinyInteger('status')->default(0);
            $table->string('start_at')->nullable();
            $table->string('end_at')->nullable();
            $table->timestamps();
        });

        Schema::connection(DB_API)->create('coupon', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name_internal');
            $table->unsignedTinyInteger('coupon_type')->default(0);
            $table->string('start_at')->nullable();
            $table->string('end_at')->nullable();
        });

        Schema::connection(DB_API)->create('user_coupon', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('rule_id')->default(0);
            $table->unsignedInteger('coupon_id')->default(0);
            $table->unsignedBigInteger('user_code')->default(0);
            $table->unsignedTinyInteger('coupon_type')->default(0);
            $table->string('triggered_at')->nullable();
            $table->string('start_at')->nullable();
            $table->string('end_at')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
        });

        Schema::connection(DB_API)->create('user_credit_record', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('user_code');
            $table->unsignedBigInteger('trade_account_id');
            $table->decimal('grant_amount', 18, 6)->default(0);
            $table->decimal('deduct_amount', 18, 6)->default(0);
            $table->decimal('unlocked_amount', 18, 6)->default(0);
            $table->string('biz_type');
            $table->unsignedBigInteger('biz_id');
            $table->timestamp('end_at')->nullable();
            $table->timestamps();
        });

        DB::connection(DB_API)->table('grant_rule')->insert([
            'id' => 10,
            'name' => 'Coupon grant rule',
            'target_type' => GrantRule::TARGET_COUPON,
            'target' => 100,
            'event_key' => 'manual',
            'status' => GrantRule::STATUS_ENABLED,
            'total_count' => 10,
            'granted_count' => 3,
            'end_at' => '2099-12-31 23:59:59',
        ]);
        DB::connection(DB_API)->table('coupon')->insert([
            'id' => 100,
            'name_internal' => 'Test coupon',
            'coupon_type' => 1,
        ]);
    }

    public function test_find_user_codes_with_existing_credit_bonus_returns_only_matching_users(): void
    {
        // Insert CreditBonus user_coupons (coupon_type=4)
        DB::connection(DB_API)->table('user_coupon')->insert([
            ['id' => 1, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 10001, 'coupon_type' => 4, 'status' => 30],
            ['id' => 2, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 10002, 'coupon_type' => 4, 'status' => 60], // voided
            ['id' => 3, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 10003, 'coupon_type' => 2, 'status' => 30], // not CreditBonus
            ['id' => 4, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 10004, 'coupon_type' => 4, 'status' => 50], // expired
            ['id' => 5, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 10005, 'coupon_type' => 4, 'status' => 40], // used coupon
            ['id' => 6, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 10006, 'coupon_type' => 4, 'status' => 40], // used coupon
        ]);

        DB::connection(DB_API)->table('user_credit_record')->insert([
            ['biz_id' => 5, 'user_code' => 10005, 'trade_account_id' => 90001, 'biz_type' => CreditBizType::Coupon->value, 'grant_amount' => 100, 'deduct_amount' => 40, 'end_at' => '2099-12-31 23:59:59'],
            ['biz_id' => 6, 'user_code' => 10006, 'trade_account_id' => 90002, 'biz_type' => CreditBizType::Coupon->value, 'grant_amount' => 100, 'deduct_amount' => 100, 'end_at' => '2000-01-01 00:00:00'],
        ]);

        $repo = new UserCouponRepository(new UserCoupon);
        $result = $repo->findUserCodesWithExistingCreditBonus([10001, 10002, 10003, 10004, 10005, 10006, 10007]);

        // Only 10001 and 10005 have active CreditBonus records or coupons; expired bonus allows reissuance.
        $this->assertSame([10001, 10005], $result);
    }

    public function test_find_user_codes_with_existing_credit_bonus_ignores_fully_deducted_bonus(): void
    {
        DB::connection(DB_API)->table('user_coupon')->insert([
            ['id' => 21, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 30001, 'coupon_type' => 4, 'status' => 40], // used coupon
            ['id' => 22, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 30002, 'coupon_type' => 4, 'status' => 40], // used coupon
            ['id' => 23, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 30003, 'coupon_type' => 4, 'status' => 40], // used coupon
            ['id' => 24, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 30004, 'coupon_type' => 4, 'status' => 40], // used coupon
        ]);

        DB::connection(DB_API)->table('user_credit_record')->insert([
            // fully deducted: reissuance allowed even though end_at is in the future
            ['biz_id' => 21, 'user_code' => 30001, 'trade_account_id' => 90011, 'biz_type' => CreditBizType::Coupon->value, 'grant_amount' => 100, 'deduct_amount' => 100, 'end_at' => '2099-12-31 23:59:59'],
            // over-deducted (defensive): reissuance allowed
            ['biz_id' => 22, 'user_code' => 30002, 'trade_account_id' => 90012, 'biz_type' => CreditBizType::Coupon->value, 'grant_amount' => 100, 'deduct_amount' => 120, 'end_at' => '2099-12-31 23:59:59'],
            // partially deducted with no expiry: still blocks
            ['biz_id' => 23, 'user_code' => 30003, 'trade_account_id' => 90013, 'biz_type' => CreditBizType::Coupon->value, 'grant_amount' => 100, 'deduct_amount' => 30, 'end_at' => null],
            // partially deducted, unexpired: still blocks
            ['biz_id' => 24, 'user_code' => 30004, 'trade_account_id' => 90014, 'biz_type' => CreditBizType::Coupon->value, 'grant_amount' => 100, 'deduct_amount' => 30, 'end_at' => '2099-12-31 23:59:59'],
        ]);

        $repo = new UserCouponRepository(new UserCoupon);
        $result = $repo->findUserCodesWithExistingCreditBonus([30001, 30002, 30003, 30004]);

        $this->assertSame([30003, 30004], $result);
    }

    public function test_find_user_codes_with_existing_credit_bonus_covers_both_credit_bonus_types(): void
    {
        DB::connection(DB_API)->table('user_coupon')->insert([
            ['id' => 11, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 20001, 'coupon_type' => TypeCoupon::RestrictedCreditBonus->value, 'status' => UserCoupon::STATUS_USABLE],
            ['id' => 12, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 20002, 'coupon_type' => TypeCoupon::RestrictedCreditBonus->value, 'status' => 40], // used coupon
            ['id' => 13, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 20003, 'coupon_type' => TypeCoupon::RestrictedCreditBonus->value, 'status' => UserCoupon::STATUS_VOIDED],
            ['id' => 14, 'rule_id' => 10, 'coupon_id' => 100, 'user_code' => 20004, 'coupon_type' => TypeCoupon::CreditBonus->value, 'status' => UserCoupon::STATUS_USABLE],
        ]);

        DB::connection(DB_API)->table('user_credit_record')->insert([
            ['biz_id' => 12, 'user_code' => 20002, 'trade_account_id' => 90003, 'biz_type' => CreditBizType::Coupon->value, 'grant_amount' => 100, 'deduct_amount' => 0, 'end_at' => '2099-12-31 23:59:59'],
        ]);

        $repo = new UserCouponRepository(new UserCoupon);
        $result = $repo->findUserCodesWithExistingCreditBonus([20001, 20002, 20003, 20004, 20005]);

        // Restricted bonus holders (20001, 20002) and general bonus holder (20004) share one bonus pool;
        // voided bonus (20003) does not block reissuance.
        $this->assertSame([20001, 20002, 20004], $result);
    }

    public function test_find_user_codes_with_existing_credit_bonus_returns_empty_for_no_matches(): void
    {
        $repo = new UserCouponRepository(new UserCoupon);
        $result = $repo->findUserCodesWithExistingCreditBonus([99999]);

        $this->assertSame([], $result);
    }

    public function test_find_user_codes_with_existing_credit_bonus_returns_empty_for_empty_input(): void
    {
        $repo = new UserCouponRepository(new UserCoupon);
        $result = $repo->findUserCodesWithExistingCreditBonus([]);

        $this->assertSame([], $result);
    }
}
