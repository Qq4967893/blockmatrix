<?php

return [
    'message' => [
        'name_exists' => '代理等級名稱已存在，重複項：:details',
        'threshold_greater_previous' => '代理等級門檻必須大於上一級配置。上一級：活躍客戶數=:prev_active_trader_threshold，團隊持倉量=:prev_team_position_size_threshold',
        'ib_commission_rule_assigned' => '該佣金規則已被其他代理商等級綁定，不可再次使用',
        'base_commission_rate_conflict' => '代理等級綁定佣金規則的保底佣金比例重複：:rate',
    ],
    'commission_status' => [
        'assigned' => '已分佣',
        'unassigned' => '未分佣',
    ],
    'export' => [
        'ib_applications' => [
            'headers' => ['用戶ID', '聯絡方式', '主要交易平台', '近三月交易量', '推廣模式', '申請時間', '操作人', '狀態', '拒絕原因'],
        ],
        'ib_accounts' => [
            'headers' => ['用戶ID', '代理ID', '是否開啟自己交易返傭', '代理等級', '上級代理ID', '用戶類型', '團隊人數', '團隊持倉量', '佣金規則', '分佣狀態', '保護期狀態', '成為代理時間', '更新時間'],
        ],
        'ib_levels' => [
            'headers' => ['等級排序', '等級名稱', '佣金配置', '是否初始預設等級', '活躍客戶數門檻', '團隊持倉量門檻', '等級考核規則', '規則鎖定', '建立時間', '更新時間'],
        ],
    ],
];
