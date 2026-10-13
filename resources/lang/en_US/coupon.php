<?php

use App\Models\Api\Coupon;
use App\Models\Api\UserCoupon;
use Scaffold\Framework\Enums\TypeCoupon;

return [
    'status' => [
        0 => 'Not enabled',
        1 => 'Enabled',
        2 => 'Voided',
        3 => 'Disabled',
        'unknown' => 'Unknown',
    ],

    'expired_status' => [
        0 => 'Not expired',
        1 => 'Expired',
        'unknown' => 'Unknown',
    ],

    'type' => [
        TypeCoupon::RestrictedCreditBonus->value => 'Restricted Credit Bonus',
        Coupon::COUPON_TYPE_CREDIT_BONUS_GROUP => 'Credit Bonus',
    ],

    'discount_method' => [
        1 => 'By loss amount',
        2 => 'By deposit amount',
        'unknown' => 'Unknown',
    ],

    'unit_type' => [
        1 => 'By lots',
        2 => 'By trading volume',
        'unknown' => 'Unknown',
    ],

    'user_coupon_status' => [
        UserCoupon::STATUS_UNCLAIMED => 'Unclaimed',
        UserCoupon::STATUS_INACTIVE => 'Inactive',
        UserCoupon::STATUS_USABLE => 'Usable',
        UserCoupon::STATUS_UNUSABLE => 'Unusable',
        UserCoupon::STATUS_EXPIRED => 'Expired',
        UserCoupon::STATUS_VOIDED => 'Voided',
        'unknown' => 'Unknown',
    ],

    'credit_bonus_status' => [
        1 => 'Available',
        0 => 'Unavailable',
        'unknown' => 'Unknown',
    ],

    'redeem_status' => [
        0 => 'Not redeemed',
        1 => 'Redeemed',
        'unknown' => 'Unknown',
    ],

    'redeem_result' => [
        0 => 'Not redeemed',
        1 => 'Partially redeemed',
        2 => 'Redeemed',
        'unknown' => 'Unknown',
    ],

    'redeem_result_loss' => [
        0 => 'Not redeemed',
        2 => 'Redeemed',
        'unknown' => 'Unknown',
    ],

    'config_field' => [
        'discount_method' => ['label' => 'Calculation basis'],
        'deposited_rate' => ['label' => 'Deposit rate (%)'],
        'discount_rate' => ['label' => 'Loss offset rate (%)', 'note' => 'Amount = Raw PnL * rate / Amount = first deposit * rate'],
        'discount_cap' => ['label' => 'Loss offset cap', 'note' => 'Calculated amount must not exceed this cap'],
        'total_amount' => ['label' => 'Total amount'],
        'unit_type' => ['label' => 'Unit type'],
        'unit_size' => ['label' => 'Unit quantity'],
        'unit_amount' => ['label' => 'Release amount (USDT)'],
        'source' => ['label' => 'Applicable symbols'],
        'unlock_status' => ['label' => 'Configure unlock conditions'],
        'unlock_unit_amount' => ['label' => 'Unlock amount', 'note' => 'When the unlock unit is trade fee, this is the fee ratio (%), e.g. 50 means 50%'],
    ],

    'active_unit_type' => [
        'lots' => 'Lots',
        'position_size' => 'Trading volume',
        'single_deposit' => 'Single deposit',
        'deposit' => 'Total deposits',
    ],

    'active_config_field' => [
        'unit_type' => ['label' => 'Calculation unit', 'description' => 'Select the basis used by the activation condition'],
        'unit_size' => ['label' => 'Unit quantity', 'description' => 'Quantity or amount required for activation'],
        'expire_days' => ['label' => 'Activation period (days)', 'description' => 'Measured in days from when the coupon is received'],
        'source' => ['label' => 'Applicable symbols', 'description' => 'All platform symbols are used when left empty'],
    ],

    'message' => [
        'active_grant_rule_conflict' => 'This coupon is linked to enabled unfinished grant rule(s): :names. Please void the rule(s) before retrying.',
        'active_grant_rule_conflict_multiple' => 'This coupon is linked to enabled unfinished grant rules: :names. Please void the related rules before retrying.',
    ],

    'validation' => [
        'name_internal_required' => 'Internal coupon name is required.',
        'name_internal_string' => 'Internal coupon name format is invalid.',
        'name_internal_max' => 'Internal coupon name must not exceed 100 characters.',
        'name_external_required' => 'External coupon name is required.',
        'name_external_array' => 'External coupon name format is invalid.',
        'name_external_min' => 'At least one external coupon name language is required.',
        'name_external_default_lang' => 'External coupon name must include English (en_US).',
        'name_external_lang_err' => 'External coupon name contains an invalid language key.',
        'name_external_value_required' => 'External coupon name contains an empty language value.',
        'rule_name_required' => 'Rule name is required.',
        'rule_name_array' => 'Rule name format is invalid.',
        'rule_name_min' => 'At least one rule name language is required.',
        'rule_name_default_lang' => 'Rule name must include English (en_US).',
        'rule_name_lang_err' => 'Rule name contains an invalid language key.',
        'rule_name_value_required' => 'Rule name contains an empty language value.',
        'coupon_type_required' => 'Coupon type is required.',
        'coupon_type_integer' => 'Coupon type format is invalid.',
        'coupon_type_in' => 'Coupon type is invalid. Only loss recovery coupons are supported.',
        'config_required' => 'Config is required.',
        'config_array' => 'Config format is invalid.',
        'start_at_required' => 'Start time is required.',
        'start_at_date_format' => 'Start time format is invalid. Expected: Y-m-d H:i:s',
        'end_at_required' => 'End time is required.',
        'end_at_date_format' => 'End time format is invalid. Expected: Y-m-d H:i:s',
        'end_at_after_start' => 'End time cannot be earlier than start time.',
        'discount_method_required' => 'Discount method is required.',
        'discount_method_integer' => 'Discount method format is invalid.',
        'discount_method_in' => 'Discount method is invalid. Supported values: 1=loss amount, 2=deposit amount.',
        'discount_rate_required' => 'Discount rate is required.',
        'discount_rate_numeric' => 'Discount rate is invalid. It must be between 0 and 100%.',
        'discount_rate_min' => 'Discount rate is invalid. It must be between 0 and 100%.',
        'discount_rate_max' => 'Discount rate is invalid. It must be between 0 and 100%.',
        'deposited_rate_required' => 'Deposit rate is required.',
        'deposited_rate_numeric' => 'Deposit rate is invalid. It must be between 0 and 100%.',
        'deposited_rate_min' => 'Deposit rate is invalid. It must be between 0 and 100%.',
        'deposited_rate_max' => 'Deposit rate is invalid. It must be between 0 and 100%.',
        'unlock_unit_amount_max' => 'When the unlock unit is trade fee, the unlock amount must be between 0 and 100%.',
        'discount_cap_required' => 'Discount cap is required.',
        'discount_cap_numeric' => 'Discount cap is invalid. It must be greater than or equal to 0.',
        'discount_cap_min' => 'Discount cap is invalid. It must be greater than or equal to 0.',
        'status_required' => 'Status is required.',
        'status_integer' => 'Status format is invalid.',
        'status_in' => 'Status is invalid. Supported values: 1=enabled, 2=voided, 3=stopped.',
    ],
];
