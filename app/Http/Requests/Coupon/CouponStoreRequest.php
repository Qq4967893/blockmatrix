<?php

namespace App\Http\Requests\Coupon;

use App\Http\Requests\ApiRequest;
use App\Models\Api\Coupon;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Enums\TypeCoupon;

class CouponStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $couponTypes = array_map(static fn (TypeCoupon $type): int => $type->value, TypeCoupon::cases());
        $couponType = TypeCoupon::tryFrom((int) $this->input('coupon_type'));

        $rules = [
            'name_internal' => ['required', 'string', 'max:100'],
            'name_external' => ['required', 'array', 'min:1'],
            'rule_name' => ['required', 'array', 'min:1'],
            'coupon_type' => ['required', 'integer', Rule::in($couponTypes)],
            'start_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'end_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'active_config' => ['nullable', 'array'],
            'active_config.unit_type' => [
                Rule::requiredIf(fn (): bool => is_array($this->input('active_config'))),
                'nullable',
                Rule::in(Coupon::activeUnitTypes()),
            ],
            'active_config.unit_size' => [
                Rule::requiredIf(fn (): bool => is_array($this->input('active_config'))),
                'nullable',
                'numeric',
                'gt:0',
            ],
            'active_config.expire_days' => [
                Rule::requiredIf(fn (): bool => is_array($this->input('active_config'))),
                'nullable',
                'integer',
                'min:1',
            ],
            'active_config.source' => ['nullable', 'array'],
            'active_config.source.*' => ['string'],
        ];

        return array_merge($rules, $this->couponConfigRules($couponType));
    }

    public function messages(): array
    {
        return [
            'name_internal.required' => trans('coupon.validation.name_internal_required'),
            'name_internal.string' => trans('coupon.validation.name_internal_string'),
            'name_internal.max' => trans('coupon.validation.name_internal_max'),
            'name_external.required' => trans('coupon.validation.name_external_required'),
            'name_external.array' => trans('coupon.validation.name_external_array'),
            'name_external.min' => trans('coupon.validation.name_external_min'),
            'rule_name.required' => trans('coupon.validation.rule_name_required'),
            'rule_name.array' => trans('coupon.validation.rule_name_array'),
            'rule_name.min' => trans('coupon.validation.rule_name_min'),
            'coupon_type.required' => trans('coupon.validation.coupon_type_required'),
            'coupon_type.integer' => trans('coupon.validation.coupon_type_integer'),
            'coupon_type.in' => trans('coupon.validation.coupon_type_in'),
            'start_at.required' => trans('coupon.validation.start_at_required'),
            'start_at.date_format' => trans('coupon.validation.start_at_date_format'),
            'end_at.required' => trans('coupon.validation.end_at_required'),
            'end_at.date_format' => trans('coupon.validation.end_at_date_format'),
            'config.required' => trans('coupon.validation.config_required'),
            'config.array' => trans('coupon.validation.config_array'),
            'config.discount_method.integer' => trans('coupon.validation.discount_method_integer'),
            'config.discount_method.in' => trans('coupon.validation.discount_method_in'),
            'config.discount_rate.numeric' => trans('coupon.validation.discount_rate_numeric'),
            'config.discount_rate.min' => trans('coupon.validation.discount_rate_min'),
            'config.discount_rate.max' => trans('coupon.validation.discount_rate_max'),
            'config.discount_cap.numeric' => trans('coupon.validation.discount_cap_numeric'),
            'config.discount_cap.min' => trans('coupon.validation.discount_cap_min'),
            'config.deposited_rate.required' => trans('coupon.validation.deposited_rate_required'),
            'config.deposited_rate.numeric' => trans('coupon.validation.deposited_rate_numeric'),
            'config.deposited_rate.min' => trans('coupon.validation.deposited_rate_min'),
            'config.deposited_rate.max' => trans('coupon.validation.deposited_rate_max'),
            'config.unlock_unit_amount.max' => trans('coupon.validation.unlock_unit_amount_max'),
            'config.total_amount.numeric' => trans('public.message.data_error'),
            'config.total_amount.min' => trans('public.message.data_error'),
            'config.unit_type.integer' => trans('public.message.data_error'),
            'config.unit_type.in' => trans('public.message.data_error'),
            'config.unit_size.numeric' => trans('public.message.data_error'),
            'config.unit_size.min' => trans('public.message.data_error'),
            'config.unit_amount.numeric' => trans('public.message.data_error'),
            'config.unit_amount.min' => trans('public.message.data_error'),
            'config.source.array' => trans('public.message.data_error'),
            'config.source.*.string' => trans('public.message.data_error'),
            'config.restricted_max_leverage.required' => trans('public.message.in_complete'),
            'config.restricted_source.present' => trans('public.message.in_complete'),
            'active_config.array' => trans('public.message.data_error'),
            'active_config.unit_type.required' => trans('public.message.in_complete'),
            'active_config.unit_type.in' => trans('public.message.wrong_options'),
            'active_config.unit_size.required' => trans('public.message.in_complete'),
            'active_config.unit_size.numeric' => trans('public.message.data_error'),
            'active_config.unit_size.gt' => trans('public.message.data_error'),
            'active_config.expire_days.required' => trans('public.message.in_complete'),
            'active_config.expire_days.integer' => trans('public.message.data_error'),
            'active_config.expire_days.min' => trans('public.message.data_error'),
            'active_config.source.array' => trans('public.message.data_error'),
            'active_config.source.*.string' => trans('public.message.data_error'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->validateLocaleMap($validator, 'name_external');
            $this->validateLocaleMap($validator, 'rule_name');
            $this->validateEffectiveTime($validator);
            $this->validateLossRecoverConfig($validator);
            $this->validateUnlockUnitAmount($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        // name_external / rule_name 支持 JSON 字符串
        foreach (['name_external', 'rule_name'] as $field) {
            $value = $this->input($field);
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([$field => $decoded]);
            }
        }

        // config 支持 JSON 字符串，并确保 source 为数组
        $config = $this->input('config');
        if (is_string($config) && trim($config) !== '') {
            $decoded = json_decode($config, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $config = $decoded;
            }
        }
        if (is_array($config)) {
            // source 及 *_source（如 unlock_source、restricted_source）统一按数组去重
            foreach ($config as $key => $value) {
                if (($key === 'source' || str_ends_with((string) $key, '_source')) && is_array($value)) {
                    $config[$key] = array_values(array_unique(
                        array_map('strval', $value)
                    ));
                }
            }
            $this->merge(['config' => $config]);
        }

        $activeConfig = $this->input('active_config');
        if (is_string($activeConfig) && trim($activeConfig) !== '') {
            $decoded = json_decode($activeConfig, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $activeConfig = $decoded;
            }
        }
        if (is_array($activeConfig)) {
            if (isset($activeConfig['source']) && is_array($activeConfig['source'])) {
                $activeConfig['source'] = array_values(array_unique(
                    array_map('strval', $activeConfig['source'])
                ));
            }
            $this->merge(['active_config' => $activeConfig]);
        }
    }

    private function validateLocaleMap($validator, string $field): void
    {
        $values = $this->input($field, []);
        if (! is_array($values)) {
            return;
        }

        $langKeys = array_keys(config('lang.languages', []));
        if (! array_key_exists('en_US', $values) || trim((string) $values['en_US']) === '') {
            $validator->errors()->add($field, trans(sprintf('coupon.validation.%s_default_lang', $field)));
        }

        foreach ($values as $lang => $value) {
            if (! in_array($lang, $langKeys, true)) {
                $validator->errors()->add($field, trans(sprintf('coupon.validation.%s_lang_err', $field)));

                return;
            }

            if (! is_string($value) || trim($value) === '') {
                $validator->errors()->add($field, trans(sprintf('coupon.validation.%s_value_required', $field)));

                return;
            }
        }
    }

    private function validateEffectiveTime($validator): void
    {
        $startAt = $this->input('start_at');
        $endAt = $this->input('end_at');
        if (! is_string($startAt) || ! is_string($endAt)) {
            return;
        }

        $startTimestamp = strtotime($startAt);
        $endTimestamp = strtotime($endAt);
        if ($startTimestamp === false || $endTimestamp === false) {
            return;
        }

        if ($endTimestamp < $startTimestamp) {
            $validator->errors()->add('end_at', trans('coupon.validation.end_at_after_start'));
        }
    }

    private function validateLossRecoverConfig($validator): void
    {
        $couponType = (int) $this->input('coupon_type');
        if ($couponType !== TypeCoupon::LossRecover->value) {
            return;
        }

        $config = $this->input('config', []);
        if (! is_array($config)) {
            return;
        }

        $discountMethod = $config['discount_method'] ?? null;
        if ($discountMethod === null || $discountMethod === '') {
            $validator->errors()->add('config.discount_method', trans('coupon.validation.discount_method_required'));
        }

        $discountCap = $config['discount_cap'] ?? null;
        if ($discountCap === null || $discountCap === '') {
            $validator->errors()->add('config.discount_cap', trans('coupon.validation.discount_cap_required'));
        }

        $rate = $config['discount_rate'] ?? null;
        if ($rate === null || $rate === '') {
            $validator->errors()->add('config.discount_rate', trans('coupon.validation.discount_rate_required'));

            return;
        }

        if (! is_numeric($rate)) {
            $validator->errors()->add('config.discount_rate', trans('coupon.validation.discount_rate_numeric'));

            return;
        }

        $rate = (float) $rate;
        if ($rate < 0) {
            $validator->errors()->add('config.discount_rate', trans('coupon.validation.discount_rate_min'));

            return;
        }

        if ($rate > 100) {
            $validator->errors()->add('config.discount_rate', trans('coupon.validation.discount_rate_max'));
        }
    }

    /**
     * 解锁单位=交易手续费时，解锁金额为手续费比例（百分点），上限 100。
     */
    private function validateUnlockUnitAmount($validator): void
    {
        $config = $this->input('config', []);
        if (! is_array($config)
            || (int) ($config['unlock_unit_type'] ?? 0) !== TypeCoupon::UNIT_TYPE_TRADE_FEE
        ) {
            return;
        }

        $amount = $config['unlock_unit_amount'] ?? null;
        if ($amount === null || $amount === '' || ! is_numeric($amount)) {
            return;
        }

        if ((float) $amount > 100) {
            $validator->errors()->add('config.unlock_unit_amount', trans('coupon.validation.unlock_unit_amount_max'));
        }
    }

    private function couponConfigRules(?TypeCoupon $couponType): array
    {
        $details = array_values(array_filter(
            TypeCoupon::details(),
            static fn (array $detail): bool => $couponType === null
                || (int) ($detail['value'] ?? 0) === $couponType->value
        ));
        $definitions = [];

        foreach ($details as $detail) {
            $requiredVariables = is_array($detail['required_variables'] ?? null)
                ? $detail['required_variables']
                : [];

            foreach ((array) ($detail['variable'] ?? []) as $variable) {
                $field = (string) ($variable['variable'] ?? '');
                if ($field === '') {
                    continue;
                }

                $definitions[$field] ??= ['required' => false, 'options' => []];
                $definitions[$field]['required'] = $definitions[$field]['required']
                    || in_array($field, $requiredVariables, true)
                    || Coupon::isConfigFieldRequired((int) ($detail['value'] ?? 0), $field);
                $definitions[$field]['options'] = $this->mergeOptionValues(
                    $definitions[$field]['options'],
                    (array) ($variable['options'] ?? [])
                );
            }

            // deposited_rate is a legacy CRM extension to the loss-recover scaffold fields.
            if ((int) ($detail['value'] ?? 0) === TypeCoupon::LossRecover->value) {
                $definitions['deposited_rate'] ??= ['required' => false, 'options' => []];
            }
        }

        $allowedFields = array_keys($definitions);
        $configRule = $allowedFields === []
            ? 'array'
            : 'array:'.implode(',', $allowedFields);
        $rules = ['config' => ['required', $configRule]];

        foreach ($definitions as $field => $definition) {
            $rules["config.{$field}"] = $this->couponConfigFieldRules(
                $field,
                (bool) $definition['required'],
                $definition['options']
            );

            if ($field === 'source' || str_ends_with($field, '_source')) {
                $rules["config.{$field}.*"] = ['string'];
            }
        }

        return $rules;
    }

    private function couponConfigFieldRules(string $field, bool $required, array $optionValues): array
    {
        if ($field === 'deposited_rate') {
            return [
                Rule::requiredIf(fn (): bool => (int) $this->input('coupon_type') === TypeCoupon::LossRecover->value
                    && (int) $this->input('config.discount_method') === TypeCoupon::DISCOUNT_METHOD_BY_DEPOSIT
                ),
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ];
        }

        // source 类字段必须提交但允许空数组（如限制型赠金券不选来源 = 不限制）
        $isSourceField = $field === 'source' || str_ends_with($field, '_source');
        $rules = [$required ? ($isSourceField ? 'present' : 'required') : 'nullable'];

        if ($optionValues !== []) {
            if (collect($optionValues)->every(static fn (mixed $value): bool => is_int($value))) {
                $rules[] = 'integer';
            } elseif (collect($optionValues)->every(static fn (mixed $value): bool => is_string($value))) {
                $rules[] = 'string';
            }
            $rules[] = Rule::in($optionValues);

            return $rules;
        }

        if ($field === 'unlock_status') {
            $rules[] = 'boolean';
        } elseif ($field === 'source' || str_ends_with($field, '_source')) {
            $rules[] = 'array';
        } elseif (str_ends_with($field, '_days')) {
            $rules[] = 'integer';
            $rules[] = 'min:1';
        } elseif (str_ends_with($field, '_rate')) {
            $rules[] = 'numeric';
            $rules[] = 'min:0';
            $rules[] = 'max:100';
        } elseif ($this->isNumericConfigField($field)) {
            $rules[] = 'numeric';
            $rules[] = 'min:0';
        }

        return $rules;
    }

    private function mergeOptionValues(array $current, array $options): array
    {
        foreach ($options as $option) {
            if (! is_array($option) || ! array_key_exists('value', $option)) {
                continue;
            }

            $current[] = $option['value'];
        }

        return array_values(array_unique($current, SORT_REGULAR));
    }

    private function isNumericConfigField(string $field): bool
    {
        foreach (['_amount', '_size', '_cap', '_leverage'] as $suffix) {
            if (str_ends_with($field, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
