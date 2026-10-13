<?php

namespace Tests\Unit\Services\Api;

use App\Constants\FieldType;
use App\Http\Requests\Coupon\CouponStoreRequest;
use App\Models\Api\Coupon;
use App\Services\Api\CouponService;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;
use Scaffold\Framework\Enums\TypeCoupon;
use Tests\TestCase;

class CouponServiceConfigTest extends TestCase
{
    public function test_deposited_rate_is_normalized_for_deposit_based_loss_recover_coupon(): void
    {
        $config = $this->invokeCouponServiceMethod('normalizeConfig', TypeCoupon::LossRecover->value, [
            'discount_method' => TypeCoupon::DISCOUNT_METHOD_BY_DEPOSIT,
            'discount_rate' => 15,
            'discount_cap' => 100,
            'deposited_rate' => 20,
        ]);

        $this->assertSame(0.2, $config['deposited_rate']);
    }

    public function test_deposited_rate_is_removed_for_loss_based_loss_recover_coupon(): void
    {
        $config = $this->invokeCouponServiceMethod('normalizeConfig', TypeCoupon::LossRecover->value, [
            'discount_method' => TypeCoupon::DISCOUNT_METHOD_BY_LOSS,
            'discount_rate' => 15,
            'discount_cap' => 100,
            'deposited_rate' => 20,
        ]);

        $this->assertArrayNotHasKey('deposited_rate', $config);
    }

    public function test_deposited_rate_is_returned_for_list_editing(): void
    {
        $coupon = new Coupon;
        $coupon->coupon_type = TypeCoupon::LossRecover->value;
        $coupon->config = [
            'discount_method' => TypeCoupon::DISCOUNT_METHOD_BY_DEPOSIT,
            'discount_rate' => 0.15,
            'discount_cap' => 100,
            'deposited_rate' => 0.2,
        ];

        $config = $this->invokeCouponServiceMethod('parseCouponConfig', $coupon);

        $this->assertSame('20.00', $config['deposited_rate']);
    }

    public function test_missing_deposited_rate_is_returned_as_null_for_old_records(): void
    {
        $coupon = new Coupon;
        $coupon->coupon_type = TypeCoupon::LossRecover->value;
        $coupon->config = [
            'discount_method' => TypeCoupon::DISCOUNT_METHOD_BY_DEPOSIT,
            'discount_rate' => 0.15,
            'discount_cap' => 100,
        ];

        $config = $this->invokeCouponServiceMethod('parseCouponConfig', $coupon);

        $this->assertArrayHasKey('deposited_rate', $config);
        $this->assertNull($config['deposited_rate']);
    }

    public function test_loss_recover_options_include_deposited_rate(): void
    {
        $options = $this->invokeCouponServiceMethod('options');
        $lossRecover = collect($options['type_detail_options'])
            ->firstWhere('code', TypeCoupon::LossRecover->value);

        $depositedRate = collect($lossRecover['variable'])->firstWhere('variable', 'deposited_rate');
        $variables = array_column($lossRecover['variable'], 'variable');
        $depositedRateIndex = array_search('deposited_rate', $variables, true);
        $discountRateIndex = array_search('discount_rate', $variables, true);

        $this->assertSame(trans('coupon.config_field.deposited_rate.label'), $depositedRate['variable_label']);
        $this->assertIsInt($depositedRateIndex);
        $this->assertIsInt($discountRateIndex);
        $this->assertSame($discountRateIndex - 1, $depositedRateIndex);
    }

    public function test_type_detail_options_use_scaffold_parameter_options(): void
    {
        $options = $this->invokeCouponServiceMethod('options');

        foreach (TypeCoupon::details() as $detail) {
            $typeDetail = collect($options['type_detail_options'])
                ->firstWhere('code', $detail['value']);

            $this->assertSame($detail['relation'] ?? [], $typeDetail['relation']);

            foreach ($detail['variable'] as $variable) {
                if (! isset($variable['options'])) {
                    continue;
                }

                $typeDetailVariable = collect($this->flattenCouponVariables($typeDetail['variable']))
                    ->firstWhere('variable', $variable['variable']);
                $isRequired = in_array(
                    $variable['variable'],
                    $detail['required_variables'] ?? [],
                    true
                );

                $this->assertSame($variable['options'], $typeDetailVariable['options']);
                $this->assertSame(FieldType::SELECT, $typeDetailVariable['type']);
                $this->assertSame($isRequired, $typeDetailVariable['is_required']);
                $this->assertSame($isRequired, $typeDetailVariable['is_select_required']);
            }
        }
    }

    public function test_required_variables_follow_scaffold_for_every_coupon_field(): void
    {
        $options = $this->invokeCouponServiceMethod('options');

        foreach (TypeCoupon::details() as $detail) {
            $typeDetail = collect($options['type_detail_options'])
                ->firstWhere('code', $detail['value']);
            $requiredVariables = $detail['required_variables'] ?? [];

            foreach ($this->flattenCouponVariables($typeDetail['variable']) as $variable) {
                $isRequired = in_array($variable['variable'], $requiredVariables, true)
                    || Coupon::isConfigFieldRequired((int) $detail['value'], $variable['variable']);

                $this->assertSame($isRequired, $variable['is_required']);
                $this->assertSame(
                    $isRequired && isset($variable['options']),
                    $variable['is_select_required']
                );
            }
        }
    }

    public function test_parameter_options_are_not_duplicated_at_top_level(): void
    {
        $options = $this->invokeCouponServiceMethod('options');

        $this->assertArrayNotHasKey('discount_method_options', $options);
        $this->assertArrayNotHasKey('unit_type_options', $options);
    }

    public function test_coupon_type_options_group_credit_bonus_types(): void
    {
        app()->setLocale('zh_CN');
        $options = $this->invokeCouponServiceMethod('options');
        $couponTypeOptions = $options['coupon_type_options'];
        $topLevelCodes = array_column($couponTypeOptions, 'code');

        $this->assertSame(
            [
                TypeCoupon::LossRecover->value,
                TypeCoupon::TradeCashback->value,
                TypeCoupon::FeeReduction->value,
                Coupon::COUPON_TYPE_CREDIT_BONUS_GROUP,
            ],
            $topLevelCodes
        );

        $group = collect($couponTypeOptions)
            ->firstWhere('code', Coupon::COUPON_TYPE_CREDIT_BONUS_GROUP);

        $this->assertSame('赠金券', $group['label']);
        $this->assertSame([
            ['code' => TypeCoupon::CreditBonus->value, 'label' => '通用赠金券'],
            ['code' => TypeCoupon::RestrictedCreditBonus->value, 'label' => '限制型赠金券'],
        ], $group['children']);
    }

    public function test_coupon_type_group_code_is_rejected_by_validation(): void
    {
        $payload = $this->validCouponPayload([
            'coupon_type' => Coupon::COUPON_TYPE_CREDIT_BONUS_GROUP,
            'config' => [],
        ]);
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());

        $this->assertTrue($validator->errors()->has('coupon_type'));
    }

    public function test_coupon_type_label_prefers_local_translation(): void
    {
        app()->setLocale('zh_CN');

        $this->assertSame('限制型赠金券', Coupon::typeLabel(TypeCoupon::RestrictedCreditBonus));
        $this->assertSame('限制型赠金券', Coupon::typeLabel(TypeCoupon::RestrictedCreditBonus->value));
        $this->assertSame('通用赠金券', Coupon::typeLabel(TypeCoupon::CreditBonus));
        $this->assertSame('抹亏券', Coupon::typeLabel(TypeCoupon::LossRecover));
        $this->assertSame('', Coupon::typeLabel(Coupon::COUPON_TYPE_CREDIT_BONUS_GROUP));
    }

    public function test_trade_account_type_options_follow_each_coupon_type(): void
    {
        $options = $this->invokeCouponServiceMethod('options');

        foreach (TypeCoupon::cases() as $couponType) {
            $typeDetail = collect($options['type_detail_options'])
                ->firstWhere('code', $couponType->value);
            $tradeAccountType = collect($typeDetail['variable'])
                ->firstWhere('variable', 'trade_account_type');

            $this->assertSame(
                TypeCoupon::parameterOptions($couponType, 'trade_account_type'),
                $tradeAccountType['options']
            );
        }
    }

    public function test_credit_bonus_unlock_status_uses_switch_control(): void
    {
        $options = $this->invokeCouponServiceMethod('options');
        $creditBonus = collect($options['type_detail_options'])
            ->firstWhere('code', TypeCoupon::CreditBonus->value);
        $unlockStatus = collect($creditBonus['variable'])
            ->firstWhere('variable', 'unlock_status');

        $this->assertSame(
            trans('coupon.config_field.unlock_status.label'),
            $unlockStatus['variable_label']
        );
        $this->assertSame(FieldType::SWITCH, $unlockStatus['type']);
        $this->assertSame('开关', $unlockStatus['type_text']);
        $this->assertFalse($unlockStatus['is_required']);
        $this->assertFalse($unlockStatus['is_select_required']);
        $this->assertArrayNotHasKey('options', $unlockStatus);
    }

    public function test_credit_bonus_unlock_fields_are_nested_under_unlock_status(): void
    {
        $options = $this->invokeCouponServiceMethod('options');
        $creditBonus = collect($options['type_detail_options'])
            ->firstWhere('code', TypeCoupon::CreditBonus->value);
        $rootVariables = collect($creditBonus['variable']);
        $unlockStatus = $rootVariables->firstWhere('variable', 'unlock_status');
        $children = collect($unlockStatus['children'])->keyBy('variable');

        $this->assertSame([
            'unlock_unit_type',
            'unlock_unit_size',
            'unlock_unit_amount',
            'unlock_source',
        ], $children->keys()->all());
        $this->assertNull($rootVariables->firstWhere('variable', 'unlock_unit_type'));
        $this->assertSame(FieldType::SELECT, $children['unlock_unit_type']['type']);
        $this->assertSame(FieldType::NUMBER, $children['unlock_unit_size']['type']);
        $this->assertSame(FieldType::NUMBER, $children['unlock_unit_amount']['type']);
        $this->assertSame(FieldType::CASCADER, $children['unlock_source']['type']);
        $this->assertSame('级联选择器', $children['unlock_source']['type_text']);
        $this->assertNotSame('', $children['unlock_unit_type']['variable_label']);
        $this->assertNotSame('', $children['unlock_source']['variable_label']);
    }

    public function test_numeric_coupon_fields_use_number_input(): void
    {
        $options = $this->invokeCouponServiceMethod('options');
        $numericFields = [
            'deposited_rate',
            'discount_rate',
            'discount_cap',
            'total_amount',
            'unit_size',
            'unit_amount',
            'expire_days',
            'unlock_unit_size',
            'unlock_unit_amount',
            'restricted_max_leverage',
        ];

        foreach ($options['type_detail_options'] as $typeDetail) {
            foreach ($this->flattenCouponVariables($typeDetail['variable']) as $variable) {
                if (! in_array($variable['variable'], $numericFields, true)) {
                    continue;
                }

                $this->assertSame(FieldType::NUMBER, $variable['type']);
                $this->assertSame('数字输入框', $variable['type_text']);
            }
        }
    }

    public function test_active_config_options_describe_switch_fields(): void
    {
        $options = $this->invokeCouponServiceMethod('options');
        $activeConfig = $options['active_config_options'];
        $variables = collect($activeConfig['variable'])->keyBy('variable');

        $this->assertSame(['and'], $activeConfig['relation']);
        $this->assertSame(
            ['unit_type', 'unit_size', 'expire_days', 'source'],
            $variables->keys()->all()
        );
        $this->assertSame(
            [
                TypeCoupon::ACTIVE_UNIT_LOTS,
                TypeCoupon::ACTIVE_UNIT_POSITION_SIZE,
                TypeCoupon::ACTIVE_UNIT_SINGLE_DEPOSIT,
                TypeCoupon::ACTIVE_UNIT_DEPOSIT,
            ],
            array_column($variables['unit_type']['options'], 'value')
        );
        $this->assertSame(FieldType::SELECT, $variables['unit_type']['type']);
        $this->assertTrue($variables['unit_type']['is_required']);
        $this->assertTrue($variables['unit_type']['is_select_required']);
        $this->assertTrue($variables['unit_size']['is_required']);
        $this->assertSame(FieldType::NUMBER, $variables['unit_size']['type']);
        $this->assertTrue($variables['expire_days']['is_required']);
        $this->assertSame(FieldType::NUMBER, $variables['expire_days']['type']);
        $this->assertFalse($variables['source']['is_required']);
        $this->assertSame(FieldType::CASCADER, $variables['source']['type']);
    }

    public function test_active_config_is_normalized_and_returned_for_editing(): void
    {
        $activeConfig = [
            'unit_type' => TypeCoupon::ACTIVE_UNIT_SINGLE_DEPOSIT,
            'unit_size' => 2000,
            'expire_days' => 2,
            'source' => ['USDT'],
        ];

        $normalized = $this->invokeCouponServiceMethod('normalizeActiveConfig', $activeConfig);
        $normalizedPayload = $this->invokeCouponServiceMethod(
            'normalizeCouponPayload',
            $this->validCouponPayload(['active_config' => $activeConfig])
        );
        $parsed = $this->invokeCouponServiceMethod('parseActiveConfig', $normalized);

        $this->assertSame([
            'unit_type' => TypeCoupon::ACTIVE_UNIT_SINGLE_DEPOSIT,
            'unit_size' => 2000.0,
            'expire_days' => 2,
            'source' => ['USDT'],
        ], $normalized);
        $this->assertSame($normalized, $normalizedPayload['active_config']);
        $this->assertSame([
            'unit_type' => TypeCoupon::ACTIVE_UNIT_SINGLE_DEPOSIT,
            'unit_size' => '2000.000000',
            'expire_days' => 2,
            'source' => ['USDT'],
        ], $parsed);
        $this->assertNull($this->invokeCouponServiceMethod('normalizeActiveConfig', null));
        $this->assertNull($this->invokeCouponServiceMethod('parseActiveConfig', null));
    }

    public function test_coupon_payload_does_not_accept_or_normalize_issuance_counts(): void
    {
        $payload = $this->validCouponPayload();
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());
        $normalized = $this->invokeCouponServiceMethod('normalizeCouponPayload', $payload);

        $this->assertFalse($validator->errors()->has('total_count'));
        $this->assertArrayNotHasKey('total_count', $normalized);
    }

    public function test_enabled_active_config_requires_its_activation_fields(): void
    {
        $payload = $this->validCouponPayload(['active_config' => []]);
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());

        $this->assertTrue($validator->errors()->has('active_config.unit_type'));
        $this->assertTrue($validator->errors()->has('active_config.unit_size'));
        $this->assertTrue($validator->errors()->has('active_config.expire_days'));
        $this->assertFalse($validator->errors()->has('active_config.source'));
    }

    public function test_active_config_accepts_scaffold_unit_types_and_can_be_disabled(): void
    {
        $activeConfig = [
            'unit_type' => TypeCoupon::ACTIVE_UNIT_LOTS,
            'unit_size' => 2,
            'expire_days' => 7,
            'source' => ['XAUUSD'],
        ];
        $enabledPayload = $this->validCouponPayload(['active_config' => $activeConfig]);
        $enabledRequest = CouponStoreRequest::create('/', 'POST', $enabledPayload);
        $enabledValidator = Validator::make(
            $enabledPayload,
            $enabledRequest->rules(),
            $enabledRequest->messages()
        );

        $this->assertFalse($enabledValidator->fails());
        $this->assertSame($activeConfig, $enabledValidator->validated()['active_config']);

        $disabledPayload = $this->validCouponPayload(['active_config' => null]);
        $disabledRequest = CouponStoreRequest::create('/', 'POST', $disabledPayload);
        $disabledValidator = Validator::make(
            $disabledPayload,
            $disabledRequest->rules(),
            $disabledRequest->messages()
        );

        $this->assertFalse($disabledValidator->fails());
        $this->assertNull($disabledValidator->validated()['active_config']);
    }

    public function test_coupon_casts_active_config_json(): void
    {
        $coupon = new Coupon;
        $coupon->setRawAttributes([
            'active_config' => json_encode([
                'unit_type' => TypeCoupon::ACTIVE_UNIT_DEPOSIT,
                'unit_size' => 500,
                'expire_days' => 3,
                'source' => [],
            ]),
        ]);

        $this->assertSame(TypeCoupon::ACTIVE_UNIT_DEPOSIT, $coupon->active_config['unit_type']);
        $this->assertSame(500, $coupon->active_config['unit_size']);
        $this->assertSame(3, $coupon->active_config['expire_days']);
    }

    public function test_deposited_rate_is_required_for_deposit_based_loss_recover_coupon(): void
    {
        $payload = $this->validCouponPayload([
            'config' => [
                'discount_method' => TypeCoupon::DISCOUNT_METHOD_BY_DEPOSIT,
                'discount_rate' => 20,
                'discount_cap' => 23,
            ],
        ]);
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());

        $this->assertTrue($validator->errors()->has('config.deposited_rate'));
    }

    public function test_deposited_rate_is_optional_for_loss_based_loss_recover_coupon(): void
    {
        $payload = $this->validCouponPayload([
            'config' => [
                'discount_method' => TypeCoupon::DISCOUNT_METHOD_BY_LOSS,
                'discount_rate' => 20,
                'discount_cap' => 23,
            ],
        ]);
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());

        $this->assertFalse($validator->errors()->has('config.deposited_rate'));
    }

    public function test_deposited_rate_is_limited_to_percentage_range(): void
    {
        $payload = $this->validCouponPayload([
            'config' => [
                'discount_method' => TypeCoupon::DISCOUNT_METHOD_BY_DEPOSIT,
                'discount_rate' => 20,
                'discount_cap' => 23,
                'deposited_rate' => 101,
            ],
        ]);
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());

        $this->assertTrue($validator->errors()->has('config.deposited_rate'));
    }

    public function test_validation_preserves_loss_recover_config_fields(): void
    {
        $payload = $this->validCouponPayload([
            'coupon_type' => TypeCoupon::LossRecover->value,
            'config' => [
                'discount_method' => TypeCoupon::DISCOUNT_METHOD_BY_DEPOSIT,
                'discount_rate' => 20,
                'discount_cap' => 23,
                'deposited_rate' => 5,
            ],
        ]);

        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validated = Validator::make($payload, $request->rules(), $request->messages())->validated();

        $this->assertSame([
            'discount_method' => TypeCoupon::DISCOUNT_METHOD_BY_DEPOSIT,
            'discount_rate' => 20,
            'discount_cap' => 23,
            'deposited_rate' => 5,
        ], $validated['config']);
    }

    public function test_validation_preserves_trade_cashback_config_fields(): void
    {
        $payload = $this->validCouponPayload([
            'coupon_type' => TypeCoupon::TradeCashback->value,
            'config' => [
                'total_amount' => 1000,
                'unit_type' => TypeCoupon::UNIT_TYPE_LOTS,
                'unit_size' => 0.2,
                'unit_amount' => 5,
                'source' => ['XAUUSD', 'EURUSD'],
            ],
        ]);

        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validated = Validator::make($payload, $request->rules(), $request->messages())->validated();

        $this->assertSame([
            'total_amount' => 1000,
            'unit_type' => TypeCoupon::UNIT_TYPE_LOTS,
            'unit_size' => 0.2,
            'unit_amount' => 5,
            'source' => ['XAUUSD', 'EURUSD'],
        ], $validated['config']);
    }

    public function test_validation_preserves_new_coupon_type_scaffold_fields(): void
    {
        $configs = [
            TypeCoupon::FeeReduction->value => [
                'trade_account_type' => TypeCoupon::parameterOptions(
                    TypeCoupon::FeeReduction,
                    'trade_account_type'
                )[0]['value'],
                'total_amount' => 100,
                'unit_type' => TypeCoupon::UNIT_TYPE_LOTS,
                'unit_size' => 0.01,
                'unit_amount' => 5,
                'source' => ['XAUUSD'],
            ],
            TypeCoupon::CreditBonus->value => [
                'trade_account_type' => TypeCoupon::parameterOptions(
                    TypeCoupon::CreditBonus,
                    'trade_account_type'
                )[0]['value'],
                'total_amount' => 1000,
                'expire_days' => 30,
                'unlock_status' => true,
                'unlock_unit_type' => TypeCoupon::UNIT_TYPE_LOTS,
                'unlock_unit_size' => 0.01,
                'unlock_unit_amount' => 10,
                'unlock_source' => ['XAUUSD'],
            ],
        ];

        foreach ($configs as $couponType => $config) {
            $payload = $this->validCouponPayload([
                'coupon_type' => $couponType,
                'config' => $config,
            ]);
            $request = CouponStoreRequest::create('/', 'POST', $payload);
            $validator = Validator::make($payload, $request->rules(), $request->messages());

            $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
            $this->assertSame($config, $validator->validated()['config']);

            $normalized = $this->invokeCouponServiceMethod('normalizeConfig', $couponType, $config);
            $coupon = new Coupon;
            $coupon->coupon_type = $couponType;
            $coupon->config = $normalized;

            $this->assertEquals($normalized, $this->invokeCouponServiceMethod('parseCouponConfig', $coupon));
        }
    }

    public function test_credit_bonus_fee_ratio_unlock_amount_is_stored_as_fraction(): void
    {
        $config = $this->invokeCouponServiceMethod('normalizeConfig', TypeCoupon::CreditBonus->value, [
            'trade_account_type' => TypeCoupon::parameterOptions(
                TypeCoupon::CreditBonus,
                'trade_account_type'
            )[0]['value'],
            'total_amount' => 1000,
            'expire_days' => 30,
            'unlock_status' => true,
            'unlock_unit_type' => TypeCoupon::UNIT_TYPE_TRADE_FEE,
            'unlock_unit_size' => 1,
            'unlock_unit_amount' => 50,
            'unlock_source' => ['XAUUSD'],
        ]);

        $this->assertSame(0.5, $config['unlock_unit_amount']);
    }

    public function test_credit_bonus_fee_ratio_unlock_amount_is_returned_as_percentage(): void
    {
        $coupon = new Coupon;
        $coupon->coupon_type = TypeCoupon::RestrictedCreditBonus->value;
        $coupon->config = [
            'trade_account_type' => TypeCoupon::parameterOptions(
                TypeCoupon::RestrictedCreditBonus,
                'trade_account_type'
            )[0]['value'],
            'total_amount' => 1000,
            'expire_days' => 30,
            'unlock_status' => true,
            'unlock_unit_type' => TypeCoupon::UNIT_TYPE_TRADE_FEE,
            'unlock_unit_size' => 1,
            'unlock_unit_amount' => 0.5,
            'unlock_source' => ['XAUUSD'],
            'restricted_max_leverage' => 20,
            'restricted_source' => ['XAUUSD'],
        ];

        $config = $this->invokeCouponServiceMethod('parseCouponConfig', $coupon);

        $this->assertSame('50.00', $config['unlock_unit_amount']);
        $this->assertSame(20, $config['restricted_max_leverage']);
    }

    public function test_credit_bonus_lots_unlock_amount_is_kept_as_absolute_amount(): void
    {
        $config = $this->invokeCouponServiceMethod('normalizeConfig', TypeCoupon::CreditBonus->value, [
            'trade_account_type' => TypeCoupon::parameterOptions(
                TypeCoupon::CreditBonus,
                'trade_account_type'
            )[0]['value'],
            'total_amount' => 1000,
            'expire_days' => 30,
            'unlock_status' => true,
            'unlock_unit_type' => TypeCoupon::UNIT_TYPE_LOTS,
            'unlock_unit_size' => 0.01,
            'unlock_unit_amount' => 10,
            'unlock_source' => ['XAUUSD'],
        ]);

        $this->assertSame(10.0, $config['unlock_unit_amount']);
    }

    public function test_validation_caps_unlock_amount_at_100_in_trade_fee_mode(): void
    {
        $baseConfig = [
            'trade_account_type' => TypeCoupon::parameterOptions(
                TypeCoupon::CreditBonus,
                'trade_account_type'
            )[0]['value'],
            'total_amount' => 1000,
            'expire_days' => 30,
            'unlock_status' => true,
            'unlock_unit_type' => TypeCoupon::UNIT_TYPE_TRADE_FEE,
            'unlock_unit_size' => 1,
            'unlock_source' => ['XAUUSD'],
        ];

        $payload = $this->validCouponPayload([
            'coupon_type' => TypeCoupon::CreditBonus->value,
            'config' => $baseConfig + ['unlock_unit_amount' => 150],
        ]);
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());
        $request->withValidator($validator);

        $this->assertTrue($validator->errors()->has('config.unlock_unit_amount'));

        $payload['config']['unlock_unit_amount'] = 100;
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());
        $request->withValidator($validator);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    public function test_every_scaffold_config_field_is_validated_and_normalized(): void
    {
        foreach (TypeCoupon::details() as $detail) {
            $couponType = TypeCoupon::from((int) $detail['value']);
            $config = [];

            foreach ($detail['variable'] as $variable) {
                $field = $variable['variable'];
                $options = $variable['options'] ?? [];
                $config[$field] = match (true) {
                    $options !== [] => $options[0]['value'],
                    $field === 'source', str_ends_with($field, '_source') => ['XAUUSD'],
                    $field === 'unlock_status' => true,
                    default => 1,
                };
            }

            $payload = $this->validCouponPayload([
                'coupon_type' => $couponType->value,
                'config' => $config,
            ]);
            $request = CouponStoreRequest::create('/', 'POST', $payload);
            $validator = Validator::make($payload, $request->rules(), $request->messages());

            $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
            $this->assertSame(array_keys($config), array_keys($validator->validated()['config']));

            $normalized = $this->invokeCouponServiceMethod('normalizeConfig', $couponType->value, $config);
            $this->assertEqualsCanonicalizing(array_keys($config), array_keys($normalized));
        }
    }

    public function test_normalization_preserves_trade_account_type_for_every_coupon_type(): void
    {
        foreach (TypeCoupon::cases() as $couponType) {
            $tradeAccountType = TypeCoupon::parameterOptions($couponType, 'trade_account_type')[0]['value'];
            $normalized = $this->invokeCouponServiceMethod('normalizeConfig', $couponType->value, [
                'trade_account_type' => $tradeAccountType,
            ]);

            $this->assertSame($tradeAccountType, $normalized['trade_account_type']);

            $coupon = new Coupon;
            $coupon->coupon_type = $couponType->value;
            $coupon->config = $normalized;
            $parsed = $this->invokeCouponServiceMethod('parseCouponConfig', $coupon);

            $this->assertSame($tradeAccountType, $parsed['trade_account_type']);
        }
    }

    public function test_restricted_credit_bonus_marks_leverage_and_source_required(): void
    {
        $options = $this->invokeCouponServiceMethod('options');
        $restricted = collect($options['type_detail_options'])
            ->firstWhere('code', TypeCoupon::RestrictedCreditBonus->value);
        $variables = collect($this->flattenCouponVariables($restricted['variable']))->keyBy('variable');

        $this->assertTrue($variables['restricted_max_leverage']['is_required']);
        $this->assertFalse($variables['restricted_max_leverage']['is_select_required']);
        $this->assertTrue($variables['restricted_source']['is_required']);
        $this->assertFalse($variables['restricted_source']['is_select_required']);
        $this->assertFalse($variables['unlock_status']['is_required']);
    }

    public function test_restricted_credit_bonus_validates_required_config_fields(): void
    {
        $payload = $this->validCouponPayload([
            'coupon_type' => TypeCoupon::RestrictedCreditBonus->value,
            'config' => [
                'trade_account_type' => TypeCoupon::parameterOptions(
                    TypeCoupon::RestrictedCreditBonus,
                    'trade_account_type'
                )[0]['value'],
                'total_amount' => 1000,
                'expire_days' => 30,
            ],
        ]);
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());

        $this->assertTrue($validator->errors()->has('config.restricted_max_leverage'));
        $this->assertTrue($validator->errors()->has('config.restricted_source'));

        $payload['config']['restricted_max_leverage'] = 500;
        $payload['config']['restricted_source'] = [];
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());

        // restricted_source 允许空数组（不选来源 = 不限制），但键必须提交
        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));

        $payload['config']['restricted_source'] = ['XAUUSD'];
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    public function test_validation_rejects_config_key_from_another_coupon_type(): void
    {
        $payload = $this->validCouponPayload([
            'coupon_type' => TypeCoupon::FeeReduction->value,
            'config' => [
                'trade_account_type' => TypeCoupon::parameterOptions(
                    TypeCoupon::FeeReduction,
                    'trade_account_type'
                )[0]['value'],
                'total_amount' => 100,
                'unit_type' => TypeCoupon::UNIT_TYPE_LOTS,
                'unit_size' => 0.01,
                'unit_amount' => 5,
                'source' => ['XAUUSD'],
                'unlock_status' => true,
            ],
        ]);
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());

        $this->assertTrue($validator->errors()->has('config'));
    }

    public function test_validation_uses_scaffold_parameter_option_values(): void
    {
        $payload = $this->validCouponPayload([
            'coupon_type' => TypeCoupon::CreditBonus->value,
            'config' => [
                'trade_account_type' => 999,
                'total_amount' => 1000,
            ],
        ]);
        $request = CouponStoreRequest::create('/', 'POST', $payload);
        $validator = Validator::make($payload, $request->rules(), $request->messages());

        $this->assertTrue($validator->errors()->has('config.trade_account_type'));
    }

    private function invokeCouponServiceMethod(string $methodName, mixed ...$args): mixed
    {
        $reflection = new ReflectionClass(CouponService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($service, ...$args);
    }

    private function flattenCouponVariables(array $variables): array
    {
        $result = [];

        foreach ($variables as $variable) {
            $children = is_array($variable['children'] ?? null) ? $variable['children'] : [];
            unset($variable['children']);
            $result[] = $variable;
            $result = [...$result, ...$this->flattenCouponVariables($children)];
        }

        return $result;
    }

    private function validCouponPayload(array $overrides = []): array
    {
        return array_replace([
            'name_internal' => 'loss_recover_test',
            'name_external' => ['en_US' => 'Loss Recover Test'],
            'rule_name' => ['en_US' => 'Loss recover rule'],
            'coupon_type' => TypeCoupon::LossRecover->value,
            'start_at' => '2026-08-04 00:00:00',
            'end_at' => '2026-08-05 00:00:00',
            'config' => [
                'discount_method' => TypeCoupon::DISCOUNT_METHOD_BY_DEPOSIT,
                'discount_rate' => 20,
                'discount_cap' => 23,
                'deposited_rate' => 5,
            ],
        ], $overrides);
    }
}
