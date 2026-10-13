<?php

return [
    'type' => [
        'chain'  => '链上转账',
        'wallet' => '钱包转账',
        'bank'   => '银行转账',
    ],
    'status' => [
        0 => '待處理',
        4 => '已通過',
        5 => '異常',
    ],
    'direction' => [
        1  => '入金',
        -1 => '出金',
    ],
    'channel' => [
        'placeholder' => '-',
    ],
    'platform' => [
        1 => 'Cobo',
        2 => 'Vault',
        3 => 'Mepay',
    ],
    'platform_group' => [
        1 => '加密货币',
        2 => '法币',
    ],
    'deposit_type' => [
        1 => '钱包转账',
        2 => '银行转账',
    ],
    'deposit_status' => [
        'first_time' => '首次入金',
        'multiple'   => '多次入金',
    ],
    'placeholder' => [
        'from'    => '-',
        'to'      => '-',
        'tx_hash' => '-',
        'network' => '-',
    ],
    'export' => [
        'title' => ['用戶ID', '入金來源', '入金去向', '訂單號', '三方訂單號', '充值類型', '支付通道', '入金幣種', '入金金額', '實際支付幣種', '實際支付金額', '系統匯率', '入金時間(GMT+0)', '哈希值', '網絡', '郵箱地址', '入金狀態', '狀態'],
    ]
];
