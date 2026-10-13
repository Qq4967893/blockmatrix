<?php

return [
    'boolean' => [
        'yes' => '是',
        'no' => '否',
    ],
    'message' => [
        'max_invest_amount_gte_follower_fund' => '可跟隨金額需大於等於跟單規模',
        'follower_fund_threshold_numeric' => '跟單規模門檻必須是數字',
        'follower_count_threshold_integer' => '跟隨人數門檻必須是整數',
        'level_threshold_must_be_greater_than_previous' => ':field 必須大於上一等級',
        'level_threshold_must_be_less_than_next' => ':field 必須小於下一等級',
        'level_has_master_users_cannot_delete' => '等級下有高手用戶，不能刪除',
        'initial_level_exists' => '已存在初始預設等級：等級:level_code。',
        'not_allow_enlist' => '當前狀態不允許上榜',
        'not_allow_master_operation' => '當前狀態不允許該操作',
        'not_allow_level_protection' => '已失效、拒絕、申請中狀態下的高手不可以設定等級保護',
        'unsupported_target_status' => '不支援的目標狀態',
        'already_public' => '已是公開高手',
        'already_private' => '已是私密高手',
        'already_offline' => '已失效',
        'already_enlisted' => '該高手已是上榜狀態',
        'status_not_changed' => '狀態未變更',
    ],
    'field' => [
        'balance_threshold' => '餘額門檻',
        'order_count_30d_threshold' => '近30天交易次數門檻',
        'profit_rate_30d_threshold' => '近30天收益率門檻',
    ],
    'master_status' => [
        '0' => '申請中',
        '1' => '正常',
        '2' => '拒絕',
        '3' => '已失效',
        '4' => '隱藏',
    ],
    'master_application_status' => [
        '1' => '處理中',
        '2' => '通過',
        '3' => '拒絕',
    ],
    'master_private' => [
        '0' => '公開展示',
        '1' => '私密高手',
    ],
    'master_type' => [
        '0' => '公開高手',
        '1' => '私密高手',
    ],
    'protection_status' => [
        'inactive' => '未生效',
        'active' => '保護中',
        'ended' => '已結束',
    ],
    'export' => [
        'master_applications' => [
            'headers' => ['交易平台', '交易類型', '帳戶資訊', '高手類型', '分潤比例', '帶單規模', '操作人', '申請時間', '審核狀態'],
        ],
        'masters' => [
            'headers' => ['高手ID', '交易帳號ID', '帳號類型', '高手等級', '餘額', '近30天交易次數', '近30天收益率', '跟單規模', '跟隨人數', '已獲得分潤金額', '分潤比例', '當前隱藏天數', '等級保護開始時間', '等級保護截止時間', '保護狀態', '原因', '狀態'],
        ],
        'commission_records' => [
            'headers' => ['訂單ID', '跟單者ID', '高手ID', '高手交易帳號ID', '本次盈利', '分潤金額', '分潤比例', '高手等級', '訂單平台時間', '分潤時間', '狀態'],
        ],
        'follow_relations' => [
            'headers' => ['高手ID', '跟單者ID', '高手交易帳號ID', '跟單者交易帳號ID', '高手交易帳號類型', '跟單者交易帳號類型', '跟隨金額', '跟隨金額（含盈虧）', '高手等級', '複製開始時間', '複製結束時間', '狀態'],
        ],
        'potential_masters' => [
            'headers' => ['用戶ID', '交易帳號ID', '帳號類型', '特殊客戶', '餘額', '近30天交易次數', '近30天收益率'],
        ],
    ],
    'follow_status' => [
        'following' => '跟隨中',
        'ended' => '已結束',
    ],
    'commission_status' => [
        'pending' => '待分潤',
        'settled' => '已分潤',
        'unknown' => '未知',
    ],
];
