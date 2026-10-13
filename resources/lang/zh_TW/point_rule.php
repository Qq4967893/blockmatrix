<?php

return [
    'status' => [
        -1        => '待启用',
        0         => '已禁用',
        1         => '已启用',
        'unknown' => '未知',
    ],

    'cycle_period' => [
        0         => '不限',
        1         => '按天',
        2         => '按周',
        3         => '按月',
        4         => '按年',
        'unknown' => '未知',
    ],

    'cycle_limit' => [
        'unique'   => '唯一',
        'loop'     => '循环',
        'limited'  => '限次',
        'unknown'  => '未知',
    ],

    'event_key' => [
        'registered'               => '注册完成',
        'open_app'                 => '访问（Web/H5）',
        'bind_tg'                  => '绑定 Telegram',
        'bind_line'                => '绑定 Line',
        'bind_node'                => '绑定 Node',
        'first_recharge'           => '首次入金',
        'recharge'                 => '单笔入金≥金额',
        'first_open_trade'         => '首次交易完成',
        'open_trade'               => '交易笔数≥次数',
        'login_streak'             => '连续登录（天）',
        'invitee_registered'       => '被邀请人注册',
        'invitee_first_open_trade' => '被邀请人首次交易',
        'prop_join'                => '参与 LimitBreak Challenge',
        'submit_survey'            => '提交问卷',
        'prop_share'               => '分享 LimitBreak 活动',
        'unknown'                  => '未知',
    ],

    'event_param' => [
        'amount'             => '金额',
        'trade_count'        => '交易次数',
        'login_streak_days'  => '连续登录天数',
    ],

    'task_type' => [
        0 => '全部',
        1 => '新手任务',
        2 => '交易任务',
        3 => '社交任务',
    ],
    'points_type' => [
        1 => 'XP',
        2 => 'Drop',
        'unknown' => 'Unknown',
    ],

    'message' => [
        'cannot_change_disabled' => '禁用的规则不可修改，请新建规则。',
        'invalid_cycle_period_day' => '循環週期指定值錯誤',
        'duplicate_trigger_identifier' => '該觸發事件的唯一標識已重複',
    ],
];
