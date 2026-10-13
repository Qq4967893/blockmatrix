<?php

return [
    'boolean' => [
        'yes' => '是',
        'no' => '否',
    ],
    'message' => [
        'max_invest_amount_gte_follower_fund' => '可跟随金额需大于等于跟单规模',
        'follower_fund_threshold_numeric' => '跟单规模门槛必须是数字',
        'follower_count_threshold_integer' => '跟随人数门槛必须是整数',
        'level_threshold_must_be_greater_than_previous' => ':field 必须大于上一等级',
        'level_threshold_must_be_less_than_next' => ':field 必须小于下一等级',
        'level_has_master_users_cannot_delete' => '等级下有高手用户，不能删除',
        'initial_level_exists' => '已存在初始默认等级：等级:level_code。',
        'not_allow_enlist' => '当前状态不允许上榜',
        'not_allow_master_operation' => '当前状态不允许该操作',
        'not_allow_level_protection' => '已失效、拒绝、申请中状态下的高手不可以设置等级保护',
        'unsupported_target_status' => '不支持的目标状态',
        'already_public' => '已是公开高手',
        'already_private' => '已是私密高手',
        'already_offline' => '已失效',
        'already_enlisted' => '该高手已是上榜状态',
        'status_not_changed' => '状态未变更',
    ],
    'field' => [
        'balance_threshold' => '余额门槛',
        'order_count_30d_threshold' => '近30天交易次数门槛',
        'profit_rate_30d_threshold' => '近30天收益率门槛',
    ],
    'master_status' => [
        '0' => '申请中',
        '1' => '正常',
        '2' => '拒绝',
        '3' => '已失效',
        '4' => '隐藏',
    ],
    'master_application_status' => [
        '1' => '处理中',
        '2' => '通过',
        '3' => '拒绝',
    ],
    'master_private' => [
        '0' => '公开展示',
        '1' => '私密高手',
    ],
    'master_type' => [
        '0' => '公开高手',
        '1' => '私密高手',
    ],
    'protection_status' => [
        'inactive' => '未生效',
        'active' => '保护中',
        'ended' => '已结束',
    ],
    'export' => [
        'master_applications' => [
            'headers' => ['交易平台', '交易类型', '账户信息', '高手类型', '分润比例', '带单规模', '操作人', '申请时间', '审核状态'],
        ],
        'masters' => [
            'headers' => ['高手ID', '交易账号ID', '账号类型', '高手等级', '余额', '近30天交易次数', '近30天收益率', '跟单规模', '跟随人数', '已获得分润金额', '分润比例', '当前隐藏天数', '等级保护开始时间', '等级保护截止时间', '保护状态', '原因', '状态'],
        ],
        'commission_records' => [
            'headers' => ['订单ID', '跟单者ID', '高手ID', '高手交易账号ID', '本次盈利', '分润金额', '分润比例', '高手等级', '订单平台时间', '分润时间', '状态'],
        ],
        'follow_relations' => [
            'headers' => ['高手ID', '跟单者ID', '高手交易账号ID', '跟单者交易账号ID', '高手交易账号类型', '跟单者交易账号类型', '跟随金额', '跟随金额（含盈亏）', '高手等级', '复制开始时间', '复制结束时间', '状态'],
        ],
        'potential_masters' => [
            'headers' => ['用户ID', '交易账号ID', '账号类型', '特殊客户', '余额', '近30天交易次数', '近30天收益率'],
        ],
    ],
    'follow_status' => [
        'following' => '跟随中',
        'ended' => '已结束',
    ],
    'commission_status' => [
        'pending' => '待分润',
        'settled' => '已分润',
        'unknown' => '未知',
    ],
];
