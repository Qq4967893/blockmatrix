<?php

return [
    'status' => [
        1 => 'Enabled',
        2 => 'Voided',
        3 => 'Not enabled',
        'unknown' => 'Unknown',
    ],
    'game_status' => [
        'warmup'   => 'Warming up',
        'apply'    => 'Application period',
        'running'  => 'In progress',
        'announce' => 'Announcement period',
        'disabled' => 'Not enabled',
        'void'     => 'Voided',
    ],
    'message' => [
        'voided_no_modify' => 'Voided matches cannot be modified',
        'voided_no_action' => 'This match is voided and cannot be processed again',
        'start_at_early'   => 'Start time cannot be earlier than now',
        'apply_at_early'   => 'Application start time cannot be earlier than now',
        'not_ended'        => 'The contest has not ended yet, settlement is unavailable',
        'fake_only'        => 'Only virtual users can be modified',
        'config_profit_pair'   => 'Both min and max profit must be provided together',
        'config_order_pair'    => 'Both min and max order count must be provided together',
        'config_position_pair' => 'Both min and max position size must be provided together',
        'config_range_invalid' => 'Minimum value cannot exceed maximum value',
        'config_max_participants_invalid' => 'Configured max participants must be less than contest max participants',
        'deadline_before_end'  => 'Registration deadline must be earlier than contest end time',
        'time_overlap'     => 'Contest time overlaps with another contest, please adjust the period',
        'apply_conflict'   => 'Conflicts with the registration period of ":name". Please enable it after the previous contest starts',
    ],
    'export' => [
        'title' => ['User ID', 'Trade Account', 'Contest Name', 'Contest Time', 'Currency', 'Deposit Amount', 'Net P&L', 'Actual Commission', 'Refunded', 'Settled At'],
        'refunded' => [true => 'Yes', false => 'No'],
        'title_user_prop_certified' => ['Competition Name', 'Competition Time', 'Creator', 'User ID', 'Status', 'Nickname', 'Ranking', 'Currency', 'Entry Funds', 'Trading Volume', 'Order Count', 'Profit/Loss', 'Rebate Rate', 'Rebate Amount', 'Actual Rebate', 'Rebate Time'],
        'status' => ['Not Participated', 'Participated', 'Finished', 'Competition Failed'],
    ]
];
