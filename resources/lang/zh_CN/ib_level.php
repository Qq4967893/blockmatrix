<?php

return [
    'message' => [
        'name_exists' => '代理等级名称已存在，重复项：:details',
        'threshold_greater_previous' => '代理等级门槛必须大于上一级配置。上一级：活跃客户数=:prev_active_trader_threshold，团队持仓量=:prev_team_position_size_threshold',
        'ib_commission_rule_assigned' => '该佣金规则已被其他代理商等级绑定，不再次使用',
        'base_commission_rate_conflict' => '代理等级绑定佣金规则的保底佣金比例重复：:rate',
    ],
    'commission_status' => [
        'assigned' => '已分佣',
        'unassigned' => '未分佣',
    ],
    'export' => [
        'ib_applications' => [
            'headers' => ['用户ID', '联系方式', '主要交易平台', '近三月交易量', '推广模式', '申请时间', '操作人', '状态', '拒绝原因'],
        ],
        'ib_accounts' => [
            'headers' => ['用户ID', '代理ID', '是否开启自己交易反佣', '代理等级', '上级代理ID', '用户类型', '团队人数', '团队持仓量', '佣金规则', '分佣状态', '保护期状态', '成为代理时间', '更新时间'],
        ],
        'ib_levels' => [
            'headers' => ['等级排序', '等级名称', '佣金配置', '是否初始默认等级', '活跃客户数门槛', '团队持仓量门槛', '等级考核规则', '规则锁定', '创建时间', '更新时间'],
        ],
    ],
];
