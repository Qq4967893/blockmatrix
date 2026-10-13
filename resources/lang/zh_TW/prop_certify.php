<?php

return [
    'status' => [
        1 => '启用',
        2 => '作废',
        3 => '未启用',
        'unknown' => '未知',
    ],
    'game_status' => [
        'warmup'   => '预热期',
        'apply'    => '报名期',
        'running'  => '比赛期',
        'announce' => '公示期',
        'disabled' => '未启用',
        'void'     => '作废',
    ],
    'message' => [
        'voided_no_modify' => '已作废的比赛不可修改',
        'voided_no_action' => '比赛已作废，无法再次操作',
        'start_at_early'   => '比赛开始时间不得早于当前时间',
        'apply_at_early'   => '报名开始时间不得早于当前时间',
        'not_ended'        => '比赛未结束，暂不可结算',
        'fake_only'        => '仅支持修改虚拟用户数据',
        'config_profit_pair'   => '盈亏最小值和最大值需同时填写',
        'config_order_pair'    => '订单量最小值和最大值需同时填写',
        'config_position_pair' => '订单金额最小值和最大值需同时填写',
        'config_range_invalid' => '最小值不能大于最大值',
        'config_max_participants_invalid' => '配置中的最大參賽人數必須小於比賽最大參賽人數',
        'deadline_before_end'  => '报名截止时间必须早于比赛结束时间',
        'time_overlap'     => '比赛时间与已有比赛重叠，请调整时间段',
        'apply_conflict'   => '与「:name」的报名期冲突，请在上场比赛开始时间后点击启用。',
    ],
    'export' => [
        'title' => ['用户ID', '交易账号', '比赛名称', '比赛时间', '币种', '充值金额', '总盈亏', '实际返现', '是否返现', '结算时间'],
        'refunded' => [true => '是', false => '否'],
        'title_user_prop_certified' => ['比赛名称', '比赛时间', '创建人', 'User ID', '状态', '昵称', '排名', '币种', '参赛资金', '交易额', '订单量', '盈亏', '返现比例', '返现金额', '实际返现', '返现时间'],
        'status' => ['Not Participated', 'Participated', 'Finished', 'Competition Failed'],
    ]
];
