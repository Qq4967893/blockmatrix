<?php

use App\Models\Api\Coupon;
use App\Models\Api\UserCoupon;
use Scaffold\Framework\Enums\TypeCoupon;

return [
    'status' => [
        0 => '未启用',
        1 => '启用',
        2 => '作废',
        3 => '停用',
        'unknown' => '未知',
    ],

    'expired_status' => [
        0 => '未过期',
        1 => '已过期',
        'unknown' => '未知',
    ],

    'type' => [
        TypeCoupon::RestrictedCreditBonus->value => '限制型赠金券',
        Coupon::COUPON_TYPE_CREDIT_BONUS_GROUP => '赠金券',
    ],

    'discount_method' => [
        1 => '按亏损金额计算',
        2 => '按入金金额计算',
        'unknown' => '未知',
    ],

    'unit_type' => [
        1 => '按手数',
        2 => '按交易量',
        'unknown' => '未知',
    ],

    'user_coupon_status' => [
        UserCoupon::STATUS_UNCLAIMED => '未领取',
        UserCoupon::STATUS_INACTIVE => '未激活',
        UserCoupon::STATUS_USABLE => '可使用',
        UserCoupon::STATUS_UNUSABLE => '不可使用',
        UserCoupon::STATUS_EXPIRED => '已过期',
        UserCoupon::STATUS_VOIDED => '已作废',
        'unknown' => '未知',
    ],

    'credit_bonus_status' => [
        1 => '可用',
        0 => '不可用',
        'unknown' => '未知',
    ],

    'redeem_status' => [
        0 => '未核销',
        1 => '已核销',
        'unknown' => '未知',
    ],

    'redeem_result' => [
        0 => '未核销',
        1 => '部分核销',
        2 => '已核销',
        'unknown' => '未知',
    ],

    'redeem_result_loss' => [
        0 => '未核销',
        2 => '核销',
        'unknown' => '未知',
    ],

    'config_field' => [
        'discount_method' => ['label' => '计算基数'],
        'deposited_rate' => ['label' => '入金比例（%）'],
        'discount_rate' => ['label' => '抹亏比例（%）', 'note' => '可获得金额=Raw PnL * 该比例 / 可获得金额=首次入金金额 * 该比例'],
        'discount_cap' => ['label' => '抹亏上限', 'note' => '按比例计算后不得超出该设置值'],
        'total_amount' => ['label' => '总额度'],
        'unit_type' => ['label' => '计算单位'],
        'unit_size' => ['label' => '单位数量'],
        'unit_amount' => ['label' => '释放金额（USDT）'],
        'source' => ['label' => '适用品种范围'],
        'unlock_status' => ['label' => '配置解锁条件'],
        'unlock_unit_amount' => ['label' => '解锁金额', 'note' => '解锁单位为交易手续费时表示手续费比例（%），如 50 表示 50%'],
    ],

    'active_unit_type' => [
        'lots' => '手数',
        'position_size' => '交易量',
        'single_deposit' => '单笔入金',
        'deposit' => '总入金',
    ],

    'active_config_field' => [
        'unit_type' => ['label' => '计算单位', 'description' => '选择激活条件的计算口径'],
        'unit_size' => ['label' => '单位数量', 'description' => '满足激活条件所需的数量或金额'],
        'expire_days' => ['label' => '激活时限（天）', 'description' => '用户获得券时开始统计，单位为天'],
        'source' => ['label' => '适用品种范围', 'description' => '不填时默认使用平台全部品种'],
    ],

    'message' => [
        'active_grant_rule_conflict' => '该优惠券已关联已启用未完成投递任务：:names，请先作废该任务后重试。',
        'active_grant_rule_conflict_multiple' => '该优惠券已关联已启用未完成投递任务：:names，请先作废关联任务后重试。',
    ],

    'validation' => [
        'name_internal_required' => '优惠券活动名（对内）不能为空',
        'name_internal_string' => '优惠券活动名（对内）格式错误',
        'name_internal_max' => '优惠券活动名（对内）长度不能超过100个字符',
        'name_external_required' => '优惠券活动名（对外）不能为空',
        'name_external_array' => '优惠券活动名（对外）数据格式错误',
        'name_external_min' => '优惠券活动名（对外）至少需要配置一种语言',
        'name_external_default_lang' => '优惠券活动名（对外）必须配置英文(en_US)',
        'name_external_lang_err' => '优惠券活动名（对外）语言类型错误',
        'name_external_value_required' => '优惠券活动名（对外）存在未填写的语言文案',
        'rule_name_required' => '规则名称不能为空',
        'rule_name_array' => '规则名称数据格式错误',
        'rule_name_min' => '规则名称至少需要配置一种语言',
        'rule_name_default_lang' => '规则名称必须配置英文(en_US)',
        'rule_name_lang_err' => '规则名称语言类型错误',
        'rule_name_value_required' => '规则名称存在未填写的语言文案',
        'coupon_type_required' => '优惠券类型不能为空',
        'coupon_type_integer' => '优惠券类型数据错误',
        'coupon_type_in' => '优惠券类型错误，目前仅支持抹亏券',
        'config_required' => '券种配置不能为空',
        'config_array' => '券种配置格式错误',
        'start_at_required' => '有效开始时间不能为空',
        'start_at_date_format' => '有效开始时间格式错误，需要满足：Y-m-d H:i:s',
        'end_at_required' => '有效结束时间不能为空',
        'end_at_date_format' => '有效结束时间格式错误，需要满足：Y-m-d H:i:s',
        'end_at_after_start' => '有效结束时间不能早于有效开始时间',
        'discount_method_required' => '计算基数不能为空',
        'discount_method_integer' => '计算基数数据错误',
        'discount_method_in' => '计算基数错误，仅支持：1按亏损金额计算，2按入金金额计算',
        'discount_rate_required' => '比例不能为空',
        'discount_rate_numeric' => '比例数据错误，需要满足：0-100%',
        'discount_rate_min' => '比例数据错误，需要满足：0-100%',
        'discount_rate_max' => '比例数据错误，需要满足：0-100%',
        'deposited_rate_required' => '入金比例不能为空',
        'deposited_rate_numeric' => '入金比例数据错误，需要满足：0-100%',
        'deposited_rate_min' => '入金比例数据错误，需要满足：0-100%',
        'deposited_rate_max' => '入金比例数据错误，需要满足：0-100%',
        'unlock_unit_amount_max' => '解锁单位为交易手续费时，解锁金额需满足：0-100%',
        'discount_cap_required' => '抹亏上限不能为空',
        'discount_cap_numeric' => '抹亏上限数据错误，需要大于等于0',
        'discount_cap_min' => '抹亏上限数据错误，需要大于等于0',
        'status_required' => '状态不能为空',
        'status_integer' => '状态数据错误',
        'status_in' => '状态选项错误，仅支持：1启用，2作废，3停用',
    ],
];
