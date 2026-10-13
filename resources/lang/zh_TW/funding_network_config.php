<?php

use Scaffold\FluxWallet\Facades\FluxWallet;

return [
    'config_type' => [
        FluxWallet::OperationWithdrawal => '出金',
        FluxWallet::OperationDeposit => '入金',
    ],
    'status' => [
        'enabled' => '啟用',
        'not_enabled' => '未啟用',
        'disabled' => '停用',
    ],
    'message' => [
        'close_before_delete' => '請先關閉配置後再執行刪除操作',
        'already_enabled' => '當前配置已啟用',
        'already_disabled' => '當前配置已停用',
        'config_conflict' => ':network 網路已關聯 PSP：:psp，不可重複配置',
        'enable_conflict' => ':network 網路已關聯 PSP：:psp，不可重複啟用',
        'invalid_state' => '路由當前狀態不允許此操作，請重新整理列表後重試',
        'duplicate_config' => ':config_type（:currency）：:network 網路 :channel 通道配置已存在，不可重複建立',
        'route_conflict' => '同類型、幣種下，:network 網路已配置，更換通道請前往已有列表修改',
    ],
];
