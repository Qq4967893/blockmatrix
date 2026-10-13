<?php

use App\Models\Api\Coupon;
use App\Models\Api\UserCoupon;
use Scaffold\Framework\Enums\TypeCoupon;

return [
    'status' => [
        0 => '未有効化',
        1 => '有効',
        2 => '無効化済み',
        3 => '停止',
        'unknown' => '不明',
    ],

    'expired_status' => [
        0 => '未期限切れ',
        1 => '期限切れ',
        'unknown' => '不明',
    ],

    'type' => [
        TypeCoupon::RestrictedCreditBonus->value => '制限付きボーナスクーポン',
        Coupon::COUPON_TYPE_CREDIT_BONUS_GROUP => 'ボーナスクーポン',
    ],

    'discount_method' => [
        1 => '損失額で計算',
        2 => '入金額で計算',
        'unknown' => '不明',
    ],

    'unit_type' => [
        1 => 'ロット数',
        2 => '取引量',
        'unknown' => '不明',
    ],

    'user_coupon_status' => [
        UserCoupon::STATUS_UNCLAIMED => '未受取',
        UserCoupon::STATUS_INACTIVE => '未有効化',
        UserCoupon::STATUS_USABLE => '利用可能',
        UserCoupon::STATUS_UNUSABLE => '利用不可',
        UserCoupon::STATUS_EXPIRED => '期限切れ',
        UserCoupon::STATUS_VOIDED => '無効化済み',
        'unknown' => '不明',
    ],

    'credit_bonus_status' => [
        1 => '利用可能',
        0 => '利用不可',
        'unknown' => '不明',
    ],

    'redeem_status' => [
        0 => '未消込',
        1 => '消込済み',
        'unknown' => '不明',
    ],

    'redeem_result' => [
        0 => '未消込',
        1 => '一部消込',
        2 => '消込済み',
        'unknown' => '不明',
    ],

    'redeem_result_loss' => [
        0 => '未消込',
        2 => '消込',
        'unknown' => '不明',
    ],

    'config_field' => [
        'discount_method' => ['label' => '計算基準'],
        'deposited_rate' => ['label' => '入金比率（%）'],
        'discount_rate' => ['label' => '損失相殺率（%）', 'note' => '取得可能額=Raw PnL * 該比率 / 取得可能額=初回入金額 * 該比率'],
        'discount_cap' => ['label' => '損失相殺上限', 'note' => '比率で計算後、この設定値を超えないこと'],
        'total_amount' => ['label' => '総額'],
        'unit_type' => ['label' => '計算単位'],
        'unit_size' => ['label' => '単位数量'],
        'unit_amount' => ['label' => '解放金額（USDT）'],
        'source' => ['label' => '適用銘柄範囲'],
        'unlock_status' => ['label' => 'ロック解除条件を設定'],
        'unlock_unit_amount' => ['label' => 'ロック解除金額', 'note' => 'ロック解除単位が取引手数料の場合は手数料比率（%）を表します（例：50 = 50%）'],
    ],

    'active_unit_type' => [
        'lots' => 'ロット数',
        'position_size' => '取引量',
        'single_deposit' => '単一入金',
        'deposit' => '入金合計',
    ],

    'active_config_field' => [
        'unit_type' => ['label' => '計算単位', 'description' => '有効化条件の計算基準を選択します'],
        'unit_size' => ['label' => '単位数量', 'description' => '有効化に必要な数量または金額'],
        'expire_days' => ['label' => '有効化期限（日）', 'description' => 'クーポン取得時から日単位で計測します'],
        'source' => ['label' => '適用銘柄範囲', 'description' => '未入力の場合はすべての銘柄が対象です'],
    ],

    'message' => [
        'active_grant_rule_conflict' => 'このクーポンは有効かつ未完了の配布タスク :names に関連付けられています。先に当該タスクを無効化してから再試行してください。',
        'active_grant_rule_conflict_multiple' => 'このクーポンは有効かつ未完了の配布タスク :names に関連付けられています。関連タスクを先に無効化してから再試行してください。',
    ],

    'validation' => [
        'name_internal_required' => '内部クーポン名は必須です。',
        'name_internal_string' => '内部クーポン名の形式が不正です。',
        'name_internal_max' => '内部クーポン名は100文字以内で入力してください。',
        'name_external_required' => '外部クーポン名は必須です。',
        'name_external_array' => '外部クーポン名の形式が不正です。',
        'name_external_min' => '外部クーポン名は少なくとも1言語分必要です。',
        'name_external_default_lang' => '外部クーポン名には英語(en_US)を含める必要があります。',
        'name_external_lang_err' => '外部クーポン名に不正な言語キーがあります。',
        'name_external_value_required' => '外部クーポン名に未入力の言語文言があります。',
        'rule_name_required' => 'ルール名は必須です。',
        'rule_name_array' => 'ルール名の形式が不正です。',
        'rule_name_min' => 'ルール名は少なくとも1言語分必要です。',
        'rule_name_default_lang' => 'ルール名には英語(en_US)を含める必要があります。',
        'rule_name_lang_err' => 'ルール名に不正な言語キーがあります。',
        'rule_name_value_required' => 'ルール名に未入力の言語文言があります。',
        'coupon_type_required' => 'クーポンタイプは必須です。',
        'coupon_type_integer' => 'クーポンタイプの形式が不正です。',
        'coupon_type_in' => 'クーポンタイプが不正です。損失回復クーポンのみ対応しています。',
        'config_required' => '券種設定は必須です',
        'config_array' => '券種設定の形式が不正です',
        'start_at_required' => '開始時刻は必須です。',
        'start_at_date_format' => '開始時刻の形式が不正です。Y-m-d H:i:s 形式で入力してください。',
        'end_at_required' => '終了時刻は必須です。',
        'end_at_date_format' => '終了時刻の形式が不正です。Y-m-d H:i:s 形式で入力してください。',
        'end_at_after_start' => '終了時刻は開始時刻より前にできません。',
        'discount_method_required' => '計算基準は必須です。',
        'discount_method_integer' => '計算基準の形式が不正です。',
        'discount_method_in' => '計算基準が不正です。1=損失額、2=入金額のみ対応しています。',
        'discount_rate_required' => '割合は必須です。',
        'discount_rate_numeric' => '割合の値が不正です。0-100% の範囲で入力してください。',
        'discount_rate_min' => '割合の値が不正です。0-100% の範囲で入力してください。',
        'discount_rate_max' => '割合の値が不正です。0-100% の範囲で入力してください。',
        'deposited_rate_required' => '入金比率は必須です。',
        'deposited_rate_numeric' => '入金比率は0-100%の範囲で入力してください。',
        'deposited_rate_min' => '入金比率は0-100%の範囲で入力してください。',
        'deposited_rate_max' => '入金比率は0-100%の範囲で入力してください。',
        'unlock_unit_amount_max' => 'ロック解除単位が取引手数料の場合、ロック解除金額は0-100%の範囲で入力してください。',
        'discount_cap_required' => '上限金額は必須です。',
        'discount_cap_numeric' => '上限金額は0以上である必要があります。',
        'discount_cap_min' => '上限金額は0以上である必要があります。',
        'status_required' => 'ステータスは必須です。',
        'status_integer' => 'ステータスの形式が不正です。',
        'status_in' => 'ステータスが不正です。1=有効、2=無効化、3=停止のみ対応しています。',
    ],
];
