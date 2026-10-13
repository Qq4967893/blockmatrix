<?php

return [
    'message' => [
        'name_exists' => 'IB level name already exists. Duplicate items: :details',
        'threshold_greater_previous' => 'IB level thresholds must be greater than the previous level. Previous: active traders=:prev_active_trader_threshold, team position size=:prev_team_position_size_threshold',
        'ib_commission_rule_assigned' => 'This commission rule has already been bound to another IB level and cannot be used again.',
        'base_commission_rate_conflict' => 'IB levels have duplicate base commission rates: :rate',
    ],
    'commission_status' => [
        'assigned' => 'Assigned',
        'unassigned' => 'Unassigned',
    ],
    'export' => [
        'ib_applications' => [
            'headers' => ['User ID', 'Contact Value', 'Primary Exchange', 'Position Size Last 3M', 'Promotion Description', 'Applied At', 'Operator', 'Status', 'Rejection Reason'],
        ],
        'ib_accounts' => [
            'headers' => ['User ID', 'IB ID', 'Self-Trading Commission', 'Level Name', 'Parent IB ID', 'User Type', 'Team Size', 'Team Position Size', 'Commission Rule', 'Commission Status', 'Protection Status', 'Activated At', 'Updated At'],
        ],
        'ib_levels' => [
            'headers' => ['Level Position', 'Level Name', 'Commission Rule', 'Initial Default Level', 'Active Trader Threshold', 'Team Position Size Threshold', 'Assessment Rule', 'Rule Locked', 'Created At', 'Updated At'],
        ],
    ],
];
