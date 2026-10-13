<?php

namespace Tests\Unit\Services\Api;

use App\Constants\FieldType;
use App\Exceptions\BusinessException;
use App\Models\Api\Coupon;
use App\Models\Api\GrantRule;
use App\Repositories\Api\CouponRepository;
use App\Services\Api\GrantRuleService;
use Carbon\Carbon;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use ReflectionClass;
use Scaffold\Framework\Enums\TypeCoupon;
use Scaffold\Framework\Enums\TypeGrantEvent;
use Scaffold\Framework\Enums\TypeTradeAccount;
use Tests\TestCase;

class GrantRuleServiceOptionsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_coupon_target_options_include_expired_status_fields(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-30 12:00:00'));

        $couponRepo = Mockery::mock(CouponRepository::class);
        $couponRepo->shouldReceive('getGrantTargetCoupons')
            ->once()
            ->andReturn([
                $this->makeCoupon(1, TypeCoupon::LossRecover, 'Active coupon', '2026-07-01 00:00:00', '2026-08-01 00:00:00'),
                $this->makeCoupon(2, TypeCoupon::LossRecover, 'Expired coupon', '2026-07-01 00:00:00', '2026-07-29 23:59:59'),
            ]);

        $options = $this->invokeGrantRuleServiceMethod($couponRepo, 'buildCouponTargetOptions');
        $children = $options[0]['children'];

        $this->assertSame(trans('grant_rule.target_option_format', [
            'name' => 'Active coupon',
            'start_at' => '2026-07-01 00:00:00',
            'end_at' => '2026-08-01 00:00:00',
        ]), $children[0]['value']);
        $this->assertArrayNotHasKey('granted_count', $children[0]);
        $this->assertArrayNotHasKey('total_count', $children[0]);
        $this->assertSame(Coupon::EXPIRED_STATUS_NOT_EXPIRED, $children[0]['expired_status']);
        $this->assertSame(trans('coupon.expired_status.0'), $children[0]['expired_status_text']);
        $this->assertSame(Coupon::EXPIRED_STATUS_EXPIRED, $children[1]['expired_status']);
        $this->assertSame(trans('coupon.expired_status.1'), $children[1]['expired_status_text']);
    }

    public function test_target_options_include_reward_and_points_groups_alongside_coupon(): void
    {
        app()->setLocale('zh_CN');

        $couponRepo = Mockery::mock(CouponRepository::class);
        $couponRepo->shouldReceive('getGrantTargetCoupons')
            ->once()
            ->andReturn([]);

        $targetOptions = $this->invokeGrantRuleServiceMethod($couponRepo, 'buildTargetOptions');

        $this->assertSame([1, 'reward', 'points'], array_column($targetOptions, 'key'));
        $this->assertSame('优惠券', $targetOptions[0]['value']);
        $this->assertSame([], $targetOptions[0]['children']);
        $this->assertSame('奖励', $targetOptions[1]['value']);
        $this->assertSame([
            ['key' => 2, 'search_key' => 'target_type', 'value' => '资金账户'],
            ['key' => 3, 'search_key' => 'target_type', 'value' => '模拟账户'],
            ['key' => 4, 'search_key' => 'target_type', 'value' => '赠金账户'],
        ], $targetOptions[1]['children']);
        $this->assertSame('积分', $targetOptions[2]['value']);
        $this->assertSame([
            ['key' => 5, 'search_key' => 'target_type', 'value' => 'XP'],
            ['key' => 6, 'search_key' => 'target_type', 'value' => 'Drop'],
        ], $targetOptions[2]['children']);
    }

    public function test_grant_rule_list_returns_inventory_from_rule(): void
    {
        $couponRepo = Mockery::mock(CouponRepository::class);
        $result = $this->invokeGrantRuleServiceMethod(
            $couponRepo,
            'formatGrantRule',
            [
                'id' => 1,
                'name' => 'Grant rule',
                'target_type' => GrantRule::TARGET_COUPON,
                'target' => 10,
                'event_key' => TypeGrantEvent::Manual->value,
                'event_params' => ['user_codes' => [10001]],
                'status' => GrantRule::STATUS_DISABLED,
                'total_count' => 20,
                'granted_count' => 8,
                'post_count' => 8,
                'start_at' => '2026-07-01 00:00:00',
                'end_at' => '2026-07-31 23:59:59',
                'coupon_name' => 'Coupon',
                'coupon_type' => TypeCoupon::LossRecover->value,
            ],
            [],
            []
        );

        $this->assertSame(8, $result['granted_count']);
        $this->assertSame(20, $result['total_count']);
        $this->assertSame(8, $result['post_count']);
    }

    public function test_event_params_text_resolves_select_option_labels(): void
    {
        app()->setLocale('zh_CN');

        $couponRepo = Mockery::mock(CouponRepository::class);
        $result = $this->invokeGrantRuleServiceMethod(
            $couponRepo,
            'buildEventParamsText',
            TypeGrantEvent::CloseTrade->value,
            [
                'relation' => 'and',
                'trade_account_type' => TypeTradeAccount::Competition->value,
            ]
        );

        $this->assertSame(['交易账号类型：模拟账户'], $result);
    }

    public function test_enable_inventory_check_uses_grant_rule_counts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-30 12:00:00'));

        $coupon = $this->makeCoupon(1, TypeCoupon::LossRecover, 'Active coupon', '2026-07-01 00:00:00', '2026-08-01 00:00:00');
        $coupon->status = Coupon::STATUS_ENABLED;
        $couponRepo = Mockery::mock(CouponRepository::class);
        $couponRepo->shouldReceive('findById')->twice()->andReturn($coupon);

        $availableRule = $this->makeGrantRule(10, 8);
        $exhaustedRule = $this->makeGrantRule(10, 10);

        $this->assertNull($this->invokeGrantRuleServiceMethod(
            $couponRepo,
            'ensureGrantRuleCanBeEnabled',
            $availableRule
        ));
        $this->expectException(BusinessException::class);

        $this->invokeGrantRuleServiceMethod(
            $couponRepo,
            'ensureGrantRuleCanBeEnabled',
            $exhaustedRule
        );
    }

    public function test_event_option_relations_come_from_scaffold_parameter_config(): void
    {
        $actualRelations = collect(GrantRule::eventOptions())
            ->mapWithKeys(static fn (array $option): array => [
                $option['key'] => $option['relation'],
            ])
            ->all();

        $this->assertSame(['and', 'or'], $actualRelations[TypeGrantEvent::OpenTrade->value]);
        $this->assertSame(['and', 'or'], $actualRelations[TypeGrantEvent::CloseTrade->value]);
        $this->assertSame([], $actualRelations[TypeGrantEvent::InviteeRegistered->value]);
        $this->assertSame([], $actualRelations[TypeGrantEvent::Registered->value]);
        $this->assertSame([], $actualRelations[TypeGrantEvent::Deposited->value]);

        $inviteeRegistered = collect(GrantRule::eventOptions())
            ->firstWhere('key', TypeGrantEvent::InviteeRegistered->value);
        $inviterUserCode = collect($inviteeRegistered['event_params'] ?? [])
            ->firstWhere('param_key', 'inviter_user_code');

        $this->assertArrayHasKey('is_select_required', $inviteeRegistered);
        $this->assertFalse($inviteeRegistered['is_select_required']);
        $this->assertSame((string) FieldType::TEXTAREA, $inviterUserCode['type']);
        $this->assertFalse($inviterUserCode['is_required']);
        $this->assertFalse($inviterUserCode['is_select_required']);
    }

    public function test_trade_account_type_uses_scaffold_select_options(): void
    {
        app()->setLocale('zh_CN');

        $scaffoldDetail = collect(TypeGrantEvent::details())
            ->firstWhere('value', TypeGrantEvent::OpenTrade->value);
        $expectedOptions = collect($scaffoldDetail['variable'] ?? [])
            ->firstWhere('variable', 'trade_account_type')['options'] ?? [];

        $openTrade = collect(GrantRule::eventOptions())
            ->firstWhere('key', TypeGrantEvent::OpenTrade->value);
        $tradeAccountType = collect($openTrade['event_params'] ?? [])
            ->firstWhere('param_key', 'trade_account_type');

        $this->assertSame((string) FieldType::SELECT, $tradeAccountType['type']);
        $this->assertSame($expectedOptions, $tradeAccountType['options']);
        $this->assertSame([
            ['value' => 0, 'label' => '统一交易帐户'],
            ['value' => 1, 'label' => '跟单账户'],
            ['value' => 2, 'label' => '模拟账户'],
            ['value' => 3, 'label' => '赠金账户'],
        ], $tradeAccountType['options']);
    }

    public function test_only_trade_account_type_param_is_select_required(): void
    {
        $options = collect(GrantRule::eventOptions());

        foreach ($options as $option) {
            foreach ($option['event_params'] as $param) {
                $expected = $param['param_key'] === 'trade_account_type';

                $this->assertSame(
                    $expected,
                    $param['is_required'],
                    "Param [{$option['key']}.{$param['param_key']}] required flag mismatch."
                );
                $this->assertSame(
                    $expected,
                    $param['is_select_required'],
                    "Param [{$option['key']}.{$param['param_key']}] select required flag mismatch."
                );
            }
        }

        $closeTrade = $options->firstWhere('key', TypeGrantEvent::CloseTrade->value);
        $tradeAccountType = collect($closeTrade['event_params'])->firstWhere('param_key', 'trade_account_type');
        $this->assertTrue($tradeAccountType['is_required']);
        $this->assertTrue($tradeAccountType['is_select_required']);
    }

    public function test_lottery_params_use_select_and_number_inputs(): void
    {
        $reflection = new ReflectionClass(GrantRule::class);
        $detectParamType = $reflection->getMethod('detectParamType');

        foreach (['identifier', 'lottery_id'] as $paramKey) {
            $this->assertSame(
                FieldType::SELECT,
                $detectParamType->invoke(null, TypeGrantEvent::Lottery->value, $paramKey)
            );
        }

        foreach (['probability', 'weight'] as $paramKey) {
            $this->assertSame(
                FieldType::NUMBER,
                $detectParamType->invoke(null, TypeGrantEvent::Lottery->value, $paramKey)
            );
        }
    }

    public function test_numeric_event_params_use_number_input(): void
    {
        $numericParamKeys = [
            'amount',
            'count',
            'position_size',
            'lots',
            'margin_order',
            'probability',
            'weight',
            'profit',
        ];

        foreach (GrantRule::eventOptions() as $eventOption) {
            foreach ($eventOption['event_params'] as $eventParam) {
                if (! in_array($eventParam['param_key'], $numericParamKeys, true)) {
                    continue;
                }

                $this->assertSame(
                    (string) FieldType::NUMBER,
                    $eventParam['type'],
                    sprintf(
                        '%s.%s should use the number input',
                        $eventOption['key'],
                        $eventParam['param_key']
                    )
                );
            }
        }
    }

    private function makeCoupon(
        int $id,
        TypeCoupon $couponType,
        string $name,
        string $startAt,
        ?string $endAt
    ): Coupon {
        $coupon = new Coupon;
        $coupon->id = $id;
        $coupon->coupon_type = $couponType->value;
        $coupon->name_internal = $name;
        $coupon->start_at = $startAt;
        $coupon->end_at = $endAt;

        return $coupon;
    }

    private function makeGrantRule(int $totalCount, int $grantedCount): GrantRule
    {
        $grantRule = new GrantRule;
        $grantRule->id = 1;
        $grantRule->target_type = GrantRule::TARGET_COUPON;
        $grantRule->target = 1;
        $grantRule->status = GrantRule::STATUS_DISABLED;
        $grantRule->total_count = $totalCount;
        $grantRule->granted_count = $grantedCount;
        $grantRule->start_at = '2026-07-01 00:00:00';
        $grantRule->end_at = '2026-08-01 00:00:00';

        return $grantRule;
    }

    private function invokeGrantRuleServiceMethod(
        CouponRepository $couponRepo,
        string $methodName,
        mixed ...$args
    ): mixed {
        $reflection = new ReflectionClass(GrantRuleService::class);
        $service = $reflection->newInstanceWithoutConstructor();

        $couponRepoProperty = $reflection->getProperty('couponRepo');
        $couponRepoProperty->setAccessible(true);
        $couponRepoProperty->setValue($service, $couponRepo);

        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($service, ...$args);
    }
}
