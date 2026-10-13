<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\UserCoupon;
use App\Services\Api\CouponService;
use ReflectionClass;
use Scaffold\Framework\Enums\TypeCoupon;
use Tests\TestCase;

class CouponServiceRedeemStatusTest extends TestCase
{
    public function test_redeem_status_options_exclude_not_redeemed_label(): void
    {
        $options = $this->invokeCouponServiceMethod('redeemStatusOptions');

        $this->assertSame([
            ['code' => 1, 'label' => trans('coupon.redeem_result.1')],
            ['code' => 2, 'label' => trans('coupon.redeem_result.2')],
        ], $options);
    }

    public function test_redeem_status_is_resolved_by_coupon_type_and_amounts(): void
    {
        $this->assertSame(0, $this->invokeCouponServiceMethod(
            'resolveRedeemStatus',
            TypeCoupon::TradeCashback->value,
            100.0,
            0.0
        ));

        $this->assertSame(1, $this->invokeCouponServiceMethod(
            'resolveRedeemStatus',
            TypeCoupon::TradeCashback->value,
            100.0,
            50.0
        ));

        $this->assertSame(2, $this->invokeCouponServiceMethod(
            'resolveRedeemStatus',
            TypeCoupon::TradeCashback->value,
            100.0,
            100.0
        ));

        $this->assertSame(2, $this->invokeCouponServiceMethod(
            'resolveRedeemStatus',
            TypeCoupon::LossRecover->value,
            100.0,
            1.0
        ));

        $this->assertSame(1, $this->invokeCouponServiceMethod(
            'resolveRedeemStatus',
            TypeCoupon::FeeReduction->value,
            100.0,
            50.0
        ));

        $this->assertSame(2, $this->invokeCouponServiceMethod(
            'resolveRedeemStatus',
            TypeCoupon::FeeReduction->value,
            100.0,
            100.0
        ));
    }

    public function test_fee_reduction_list_hides_trade_order_fields(): void
    {
        app()->setLocale('zh_CN');

        $payloads = [
            'trade_order:PT117836775891500100001' => [
                'position_size' => 100,
                'trade_volume' => 0.25,
            ],
        ];
        $record = (object) [
            'id' => 11,
            'biz_id' => 'PT117836775891500100001',
            'trade_account_id' => 88,
            'trade_account_type' => 0,
            'user_code' => 10001,
            'name_internal' => '手续费减免券-S1',
            'name_external' => json_encode(['zh_CN' => '测试手续费减免券-S1'], JSON_UNESCAPED_UNICODE),
            'coupon_type' => TypeCoupon::FeeReduction->value,
            'triggered_at' => '2026-09-01 10:00:00',
            'used_at' => '2026-09-02 10:00:00',
            'start_at' => '2026-09-01 10:00:00',
            'end_at' => '2026-09-30 10:00:00',
            'total_amount' => 100,
            'used_amount' => 50,
            'status' => UserCoupon::STATUS_USABLE,
            'operator_name' => 'admin',
        ];

        $result = $this->invokeCouponServiceMethod('formatUserCoupon', $record, $payloads);

        $this->assertNull($result['biz_id']);
        $this->assertNull($result['position_size']);
        $this->assertNull($result['trade_volume']);
        $this->assertSame(1, $result['redeem_status']);
        $this->assertSame(trans('coupon.redeem_result.1'), $result['redeem_status_text']);
    }

    public function test_trade_cashback_list_keeps_trade_order_fields(): void
    {
        app()->setLocale('zh_CN');

        $payloads = [
            'trade_order:PT117836775891500100001' => [
                'position_size' => 100,
                'trade_volume' => 0.25,
            ],
        ];
        $record = (object) [
            'id' => 12,
            'biz_id' => 'PT117836775891500100001',
            'event_data' => json_encode(['ticket' => 'PT117836775891500100001'], JSON_UNESCAPED_SLASHES),
            'trade_account_id' => 88,
            'trade_account_type' => 0,
            'user_code' => 10001,
            'name_internal' => '交易返现券-S1',
            'name_external' => json_encode(['zh_CN' => '测试交易返现券-S1'], JSON_UNESCAPED_UNICODE),
            'coupon_type' => TypeCoupon::TradeCashback->value,
            'triggered_at' => '2026-09-01 10:00:00',
            'used_at' => '2026-09-02 10:00:00',
            'start_at' => '2026-09-01 10:00:00',
            'end_at' => '2026-09-30 10:00:00',
            'total_amount' => 100,
            'used_amount' => 50,
            'status' => UserCoupon::STATUS_USABLE,
            'operator_name' => 'admin',
        ];

        $result = $this->invokeCouponServiceMethod('formatUserCoupon', $record, $payloads);

        $this->assertSame('PT117836775891500100001', $result['biz_id']);
        $this->assertSame('100.000000', $result['position_size']);
        $this->assertSame('0.25', $result['trade_volume']);
        $this->assertSame(1, $result['redeem_status']);
    }

    public function test_collect_biz_ids_skips_fee_reduction_coupons(): void
    {
        $result = $this->invokeCouponServiceMethod('collectBizIdsByType', [
            (object) [
                'biz_id' => 'PT117836775891500100001',
                'coupon_type' => TypeCoupon::FeeReduction->value,
            ],
            (object) [
                'biz_id' => '',
                'coupon_type' => TypeCoupon::TradeCashback->value,
                'event_data' => json_encode(['ticket' => 'PT117836775891500100002'], JSON_UNESCAPED_SLASHES),
            ],
        ]);

        $this->assertSame(['trade_order' => ['PT117836775891500100002']], $result);
    }

    public function test_loss_recover_redeemed_label_matches_common_redeem_result_label(): void
    {
        $label = $this->invokeCouponServiceMethod(
            'resolveRedeemStatusLabel',
            TypeCoupon::LossRecover->value,
            2
        );

        $this->assertSame(trans('coupon.redeem_result.2'), $label);
    }

    public function test_user_coupon_status_labels_use_framework_status_values(): void
    {
        $expectedLabels = [
            UserCoupon::STATUS_UNCLAIMED => ['未领取', 'Unclaimed'],
            UserCoupon::STATUS_INACTIVE => ['未激活', 'Inactive'],
            UserCoupon::STATUS_USABLE => ['可使用', 'Usable'],
            UserCoupon::STATUS_UNUSABLE => ['不可使用', 'Unusable'],
            UserCoupon::STATUS_EXPIRED => ['已过期', 'Expired'],
            UserCoupon::STATUS_VOIDED => ['已作废', 'Voided'],
        ];

        app()->setLocale('zh_CN');

        foreach ($expectedLabels as $status => [$zhLabel, $enLabel]) {
            $key = sprintf('coupon.user_coupon_status.%d', $status);

            $this->assertSame($zhLabel, trans($key, [], 'zh_CN'));
            $this->assertSame($enLabel, trans($key, [], 'en_US'));
            $this->assertSame(
                $zhLabel,
                $this->invokeCouponServiceMethod('userCouponStatusLabel', $status)
            );
        }
    }

    public function test_trade_account_type_label_resolves_zero_value(): void
    {
        app()->setLocale('zh_CN');

        $this->assertSame(
            trans('scaffold::type_trade_account.0'),
            $this->invokeCouponServiceMethod('tradeAccountTypeLabel', 0)
        );
    }

    public function test_trade_cashback_detail_uses_trade_position_size(): void
    {
        $detail = $this->invokeCouponServiceMethod(
            'formatTradeCashbackDetail',
            (object) [
                'id' => 9,
                'biz_id' => '',
                'event_data' => json_encode([
                    'ticket' => 'PT117836775891500100001',
                    'close_time' => '2026-07-10 10:00:06',
                    'unit_value' => 1,
                ], JSON_UNESCAPED_SLASHES),
            ],
            [
                'trade_order:PT117836775891500100001' => [
                    'open_time' => '2026-07-10 09:00:00',
                    'close_time' => '2026-07-10 11:00:00',
                    'position_size' => 100,
                    'trade_volume' => 0.25,
                ],
            ]
        );

        $this->assertSame([
            'id' => 9,
            'ticket' => 'PT117836775891500100001',
            'position_size' => '100.000000',
            'trade_volume' => '0.25',
            'open_time' => '2026-07-10 09:00:00',
            'close_time' => '2026-07-10 10:00:06',
        ], $detail);
    }

    public function test_trade_cashback_detail_uses_dash_when_event_close_time_is_zero(): void
    {
        $detail = $this->invokeCouponServiceMethod(
            'formatTradeCashbackDetail',
            (object) [
                'id' => 10,
                'biz_id' => 'PT117888374063700100001',
                'event_data' => json_encode([
                    'ticket' => 'PT117888374063700100001',
                    'close_time' => 0,
                ], JSON_UNESCAPED_SLASHES),
            ],
            [
                'trade_order:PT117888374063700100001' => [
                    'open_time' => '2026-09-08 03:16:46',
                    'close_time' => '2026-09-08 03:51:05',
                    'position_size' => 1191615.443,
                    'trade_volume' => 15.1,
                ],
            ]
        );

        $this->assertSame('-', $detail['close_time']);
    }

    public function test_trade_cashback_detail_uses_dash_when_trade_is_not_closed(): void
    {
        $detail = $this->invokeCouponServiceMethod(
            'formatTradeCashbackDetail',
            (object) [
                'id' => 10,
                'biz_id' => 'PT117888374063700100001',
                'event_data' => json_encode([
                    'ticket' => 'PT117888374063700100001',
                    'close_time' => 0,
                ], JSON_UNESCAPED_SLASHES),
            ],
            [
                'trade_order:PT117888374063700100001' => [
                    'open_time' => '2026-09-08 03:16:46',
                    'close_time' => null,
                    'position_size' => 1191615.443,
                    'trade_volume' => 15.1,
                ],
            ]
        );

        $this->assertSame('-', $detail['close_time']);
    }

    public function test_multiple_active_grant_rule_conflict_message_includes_rule_names(): void
    {
        app()->setLocale('zh_CN');

        $names = $this->invokeCouponServiceMethod('formatGrantRuleNames', ['投递任务1', '投递任务2']);
        $message = trans('coupon.message.active_grant_rule_conflict_multiple', [
            'names' => $names,
        ]);

        $this->assertSame('投递任务1、投递任务2', $names);
        $this->assertSame('该优惠券已关联已启用未完成投递任务：投递任务1、投递任务2，请先作废关联任务后重试。', $message);
    }

    private function invokeCouponServiceMethod(string $methodName, mixed ...$args): mixed
    {
        $reflection = new ReflectionClass(CouponService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($service, ...$args);
    }
}
