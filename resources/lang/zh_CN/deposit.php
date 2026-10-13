<?php

return [
    'type' => [
        'chain'  => '链上转账',
        'wallet' => '钱包转账',
        'bank'   => '银行转账',
    ],
    'status' => [
        0 => '待处理',
        4 => '已通过',
        5 => '异常',
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
        'title' => ['用户ID', '入金来源', '入金去向', '订单号', '三方订单号', '充值类型', '支付通道', '入金币种', '入金金额', '实际支付币种', '实际支付金额', '系统汇率', '入金时间(GMT+0)', '哈希值', '网络', '邮箱地址', '入金状态', '状态'],
    ]
];
