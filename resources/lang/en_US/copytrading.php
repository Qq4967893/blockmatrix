<?php

return [
    'boolean' => [
        'yes' => 'Yes',
        'no' => 'No',
    ],
    'message' => [
        'max_invest_amount_gte_follower_fund' => 'Max followable amount must be greater than or equal to follow scale threshold.',
        'follower_fund_threshold_numeric' => 'Follow scale threshold must be a number.',
        'follower_count_threshold_integer' => 'Follow count threshold must be an integer.',
        'level_threshold_must_be_greater_than_previous' => ':field must be greater than the previous level.',
        'level_threshold_must_be_less_than_next' => ':field must be less than the next level.',
        'level_has_master_users_cannot_delete' => 'This level has master users and cannot be deleted.',
        'initial_level_exists' => 'Initial default level already exists: :level_code.',
        'not_allow_master_operation' => 'The current status does not allow this operation.',
        'not_allow_level_protection' => 'Expired, rejected, or applying masters cannot have level protection set.',
        'unsupported_target_status' => 'Unsupported target status.',
        'already_public' => 'Already a public master.',
        'already_private' => 'Already a private master.',
        'already_offline' => 'Already expired.',
    ],
    'field' => [
        'balance_threshold' => 'Balance Threshold',
        'order_count_30d_threshold' => '30D Order Count Threshold',
        'profit_rate_30d_threshold' => '30D Profit Rate Threshold',
    ],
    'master_status' => [
        '0' => 'Applying',
        '1' => 'Normal',
        '2' => 'Rejected',
        '3' => 'Expired',
        '4' => 'Hidden',
    ],
    'master_application_status' => [
        '1' => 'Processing',
        '2' => 'Approved',
        '3' => 'Rejected',
    ],
    'master_private' => [
        '0' => 'Public',
        '1' => 'Private',
    ],
    'master_type' => [
        '0' => 'Public Master',
        '1' => 'Private Master',
    ],
    'protection_status' => [
        'inactive' => 'Not Started',
        'active' => 'Protected',
        'ended' => 'Ended',
    ],
    'export' => [
        'master_applications' => [
            'headers' => ['Trading Platform', 'Trading Type', 'Account Information', 'Master Type', 'Profit Share Rate', 'Initial Funding', 'Operator', 'Application Time', 'Review Status'],
        ],
        'masters' => [
            'headers' => ['Master ID', 'Trade Account ID', 'Account Type', 'Master Level', 'Balance', '30D Orders', '30D Profit Rate', 'Follow Scale', 'Follow Count', 'Obtained Profit Share', 'Profit Share Rate', 'Hidden Days', 'Level Protection Start Time', 'Level Protection End Time', 'Protection Status', 'Reason', 'Status'],
        ],
        'commission_records' => [
            'headers' => ['Order ID', 'Follower ID', 'Master ID', 'Master Trade Account ID', 'Profit Amount', 'Profit Share Amount', 'Profit Share Rate', 'Master Level', 'Order Close Time', 'Profit Time', 'Status'],
        ],
        'follow_relations' => [
            'headers' => ['Master ID', 'Follower ID', 'Master Trade Account ID', 'Follower Trade Account ID', 'Master Trade Account Type', 'Follower Trade Account Type', 'Follow Amount', 'Follow Amount (Incl. P&L)', 'Master Level', 'Copy Start Time', 'Copy End Time', 'Status'],
        ],
        'potential_masters' => [
            'headers' => ['User ID', 'Trade Account ID', 'Account Type', 'Special Customer', 'Balance', '30D Orders', '30D Profit Rate'],
        ],
    ],
    'follow_status' => [
        'following' => 'Following',
        'ended' => 'Ended',
    ],
    'commission_status' => [
        'pending' => 'Pending',
        'settled' => 'Settled',
        'unknown' => 'Unknown',
    ],
];
