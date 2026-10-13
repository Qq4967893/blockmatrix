<?php

return [
    'status' => [
        -1        => 'Pending Activation',
        0       => 'Disabled',
        1       => 'Enabled',
        'unknown' => 'Unknown',
    ],

    'cycle_period' => [
        0         => 'Unlimited',
        1         => 'Daily',
        2         => 'Weekly',
        3         => 'Monthly',
        4         => 'Yearly',
        'unknown' => 'Unknown',
    ],

    'cycle_limit' => [
        'unique'   => 'Unique',
        'loop'     => 'Loop',
        'limited'  => 'Limited',
        'unknown'  => 'Unknown',
    ],

    'event_key' => [
        'registered'               => 'Registration Completed',
        'open_app'                 => 'Visit (Web/H5)',
        'bind_tg'                  => 'Bind Telegram',
        'bind_line'                => 'Bind Line',
        'bind_node'                => 'Bind Node',
        'first_recharge'           => 'First Deposit',
        'recharge'                 => 'Single Deposit ≥ Amount',
        'first_open_trade'         => 'First Trade Completed',
        'open_trade'               => 'Trades ≥ Count',
        'login_streak'             => 'Consecutive Logins (Days)',
        'invitee_registered'       => 'Invitee Registered',
        'invitee_first_open_trade' => 'Invitee First Trade',
        'prop_join'                => 'Join LimitBreak Challenge',
        'submit_survey'            => 'Submit Survey',
        'prop_share'               => 'Share LimitBreak Event',
        'unknown'                  => 'Unknown',
    ],

    'event_param' => [
        'amount'             => 'Amount',
        'trade_count'        => 'Trade Count',
        'login_streak_days'  => 'Consecutive Login Days',
    ],

    'task_type' => [
        0 => 'All',
        1 => 'Newbie Task',
        2 => 'Trading Task',
        3 => 'Social Task',
    ],
    'points_type' => [
        1 => 'XP',
        2 => 'Drop',
        'unknown' => 'Unknown',
    ],

    'message' => [
        'cannot_change_disabled' => 'Disabled rules cannot be changed, please create a new rule',
        'invalid_cycle_period_day' => 'Invalid cycle period day value.',
        'duplicate_trigger_identifier' => 'The trigger event identifier already exists',
    ],
];
