<?php

return [
    'type' => [
        'chain'  => 'On-chain transfer',
        'wallet' => 'Wallet transfer',
        'bank'   => 'Bank transfer',
    ],
    'status' => [
        0 => 'pending',
        4 => 'approved',
        5 => 'Abnormal',
    ],
    'direction' => [
        1  => 'Deposit',
        -1 => 'Withdrawal',
    ],
    'channel' => [
        'placeholder' => 'Demo channel',
    ],
    'platform' => [
        1 => 'Cobo',
        2 => 'Vault',
        3 => 'Mepay',
    ],
    'platform_group' => [
        1 => 'Crypto',
        2 => 'Fiat',
    ],
    'deposit_type' => [
        1 => 'Wallet transfer',
        2 => 'Bank transfer',
    ],
    'deposit_status' => [
        'first_time' => 'First deposit',
        'multiple'   => 'Multiple deposits',
    ],
    'placeholder' => [
        'from'    => '-',
        'to'      => '-',
        'tx_hash' => '-',
        'network' => '-',
    ],
    'export' => [
        'title' => ['User ID', 'From', 'To', 'Order No.', 'Third-party Order No.', 'Deposit Type', 'Payment Channel', 'Deposit Currency', 'Deposit Amount', 'Actual Payment Currency', 'Actual Payment Amount', 'System Rate', 'Deposit Time (GMT+0)', 'Tx Hash', 'Network', 'Email', 'Deposit Status', 'Status'],
    ]
];
