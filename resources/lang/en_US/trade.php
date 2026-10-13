<?php

return [
    'direction' => [
        'trade' => [
            0 => 'Buy',
            1 => 'Sell',
        ],
        'pending' => [
            2 => 'Pending Buy',
            3 => 'Pending Sell',
            4 => 'Buy Stop',
            5 => 'Sell Stop',
            -1 => '',
        ],
    ],
    'order_status' => [
        0 => 'No',
        1 => 'Yes',
    ],
    'close_type' => [
        -1 => '',
        0 => 'Manual Close',
        1 => 'Manual Close',
        2 => 'Admin Close',
        3 => 'Stop Loss',
        4 => 'Take Profit',
        5 => 'Forced Close',
        9 => 'Follow Close',
    ],
    'pending_status' => [
        0 => 'Pending',
        1 => 'Executed',
        2 => 'Expired',
        3 => 'Cancelled',
    ],
    'export' => [
        'trades' => [
            'headers' => ['User ID', 'Sub Account ID', 'Account Type', 'Order ID', 'Symbol', 'Lots', 'Margin Mode', 'Direction', 'Leverage', 'Open Time (GMT+0)', 'Close Time (GMT+0)', 'Open Price', 'Close Price', 'Margin', 'Profit', 'Open/Close Fee', 'Funding Fee', 'Closed', 'Close Type'],
        ],
        'pending_trades' => [
            'headers' => ['User ID', 'Sub Account', 'Order ID', 'Symbol', 'Lots', 'Margin Mode', 'Direction', 'Leverage', 'Pending Time (GMT+0)', 'Expiration Time (GMT+0)', 'Pending Price', 'Order Amount', 'Take Profit', 'Stop Loss', 'Pending Status'],
        ],
    ],
];
