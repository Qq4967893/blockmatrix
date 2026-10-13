<?php

use App\Models\Api\Coupon;
use App\Models\Api\UserCoupon;
use Scaffold\Framework\Enums\TypeCoupon;

return [
    'status' => [
        0 => '未啟用',
        1 => '啟用',
        2 => '作廢',
        3 => '停用',
        'unknown' => '未知',
    ],

    'expired_status' => [
        0 => '未過期',
        1 => '已過期',
        'unknown' => '未知',
    ],

    'type' => [
        TypeCoupon::RestrictedCreditBonus->value => '限制型贈金券',
        Coupon::COUPON_TYPE_CREDIT_BONUS_GROUP => '贈金券',
    ],

    'discount_method' => [
        1 => '按虧損金額計算',
        2 => '按入金金額計算',
        'unknown' => '未知',
    ],

    'unit_type' => [
        1 => '按手數',
        2 => '按交易量',
        'unknown' => '未知',
    ],

    'user_coupon_status' => [
        UserCoupon::STATUS_UNCLAIMED => '未領取',
        UserCoupon::STATUS_INACTIVE => '未啟用',
        UserCoupon::STATUS_USABLE => '可使用',
        UserCoupon::STATUS_UNUSABLE => '不可使用',
        UserCoupon::STATUS_EXPIRED => '已過期',
        UserCoupon::STATUS_VOIDED => '已作廢',
        'unknown' => '未知',
    ],

    'credit_bonus_status' => [
        1 => '可用',
        0 => '不可用',
        'unknown' => '未知',
    ],

    'redeem_status' => [
        0 => '未核銷',
        1 => '已核銷',
        'unknown' => '未知',
    ],

    'redeem_result' => [
        0 => '未核銷',
        1 => '部分核銷',
        2 => '已核銷',
        'unknown' => '未知',
    ],

    'redeem_result_loss' => [
        0 => '未核銷',
        2 => '核銷',
        'unknown' => '未知',
    ],

    'config_field' => [
        'discount_method' => ['label' => '計算基數'],
        'deposited_rate' => ['label' => '入金比例（%）'],
        'discount_rate' => ['label' => '抹虧比例（%）', 'note' => '可獲金額=Raw PnL * 該比例 / 可獲金額=首次入金金額 * 該比例'],
        'discount_cap' => ['label' => '抹虧上限', 'note' => '按比例計算後不得超出該設置值'],
        'total_amount' => ['label' => '總額度'],
        'unit_type' => ['label' => '計算單位'],
        'unit_size' => ['label' => '單位數量'],
        'unit_amount' => ['label' => '釋放金額（USDT）'],
        'source' => ['label' => '適用品種範圍'],
        'unlock_status' => ['label' => '配置解鎖條件'],
        'unlock_unit_amount' => ['label' => '解鎖金額', 'note' => '解鎖單位為交易手續費時表示手續費比例（%），如 50 表示 50%'],
    ],

    'active_unit_type' => [
        'lots' => '手數',
        'position_size' => '交易量',
        'single_deposit' => '單筆入金',
        'deposit' => '總入金',
    ],

    'active_config_field' => [
        'unit_type' => ['label' => '計算單位', 'description' => '選擇啟用條件的計算方式'],
        'unit_size' => ['label' => '單位數量', 'description' => '滿足啟用條件所需的數量或金額'],
        'expire_days' => ['label' => '啟用期限（天）', 'description' => '使用者獲得券時開始統計，單位為天'],
        'source' => ['label' => '適用品種範圍', 'description' => '不填時預設使用平台全部品種'],
    ],

    'message' => [
        'active_grant_rule_conflict' => '該優惠券已關聯已啟用未完成投遞任務：:names，請先作廢該任務後重試。',
        'active_grant_rule_conflict_multiple' => '該優惠券已關聯已啟用未完成投遞任務：:names，請先作廢關聯任務後重試。',
    ],

    'validation' => [
        'name_internal_required' => '優惠券活動名（對內）不能為空',
        'name_internal_string' => '優惠券活動名（對內）格式錯誤',
        'name_internal_max' => '優惠券活動名（對內）長度不能超過100個字元',
        'name_external_required' => '優惠券活動名（對外）不能為空',
        'name_external_array' => '優惠券活動名（對外）資料格式錯誤',
        'name_external_min' => '優惠券活動名（對外）至少需要配置一種語言',
        'name_external_default_lang' => '優惠券活動名（對外）必須配置英文(en_US)',
        'name_external_lang_err' => '優惠券活動名（對外）語言類型錯誤',
        'name_external_value_required' => '優惠券活動名（對外）存在未填寫的語言文案',
        'rule_name_required' => '規則名稱不能為空',
        'rule_name_array' => '規則名稱資料格式錯誤',
        'rule_name_min' => '規則名稱至少需要配置一種語言',
        'rule_name_default_lang' => '規則名稱必須配置英文(en_US)',
        'rule_name_lang_err' => '規則名稱語言類型錯誤',
        'rule_name_value_required' => '規則名稱存在未填寫的語言文案',
        'coupon_type_required' => '優惠券類型不能為空',
        'coupon_type_integer' => '優惠券類型資料錯誤',
        'coupon_type_in' => '優惠券類型錯誤，目前僅支援抹虧券',
        'config_required' => '券種配置不能為空',
        'config_array' => '券種配置格式錯誤',
        'start_at_required' => '有效開始時間不能為空',
        'start_at_date_format' => '有效開始時間格式錯誤，需要滿足：Y-m-d H:i:s',
        'end_at_required' => '有效結束時間不能為空',
        'end_at_date_format' => '有效結束時間格式錯誤，需要滿足：Y-m-d H:i:s',
        'end_at_after_start' => '有效結束時間不能早於有效開始時間',
        'discount_method_required' => '計算基數不能為空',
        'discount_method_integer' => '計算基數資料錯誤',
        'discount_method_in' => '計算基數錯誤，僅支援：1按虧損金額計算，2按入金金額計算',
        'discount_rate_required' => '比例不能為空',
        'discount_rate_numeric' => '比例資料錯誤，需要滿足：0-100%',
        'discount_rate_min' => '比例資料錯誤，需要滿足：0-100%',
        'discount_rate_max' => '比例資料錯誤，需要滿足：0-100%',
        'deposited_rate_required' => '入金比例不能為空',
        'deposited_rate_numeric' => '入金比例資料錯誤，需要滿足：0-100%',
        'deposited_rate_min' => '入金比例資料錯誤，需要滿足：0-100%',
        'deposited_rate_max' => '入金比例資料錯誤，需要滿足：0-100%',
        'unlock_unit_amount_max' => '解鎖單位為交易手續費時，解鎖金額需滿足：0-100%',
        'discount_cap_required' => '抹虧上限不能為空',
        'discount_cap_numeric' => '抹虧上限資料錯誤，需要大於等於0',
        'discount_cap_min' => '抹虧上限資料錯誤，需要大於等於0',
        'status_required' => '狀態不能為空',
        'status_integer' => '狀態資料錯誤',
        'status_in' => '狀態選項錯誤，僅支援：1啟用，2作廢，3停用',
    ],
];
