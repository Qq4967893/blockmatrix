<?php

use Scaffold\FluxWallet\Facades\FluxWallet;

return [
    'config_type' => [
        FluxWallet::OperationWithdrawal => '出金',
        FluxWallet::OperationDeposit => '入金',
    ],
    'status' => [
        'enabled' => '启用',
        'not_enabled' => '未启用',
        'disabled' => '停用',
    ],
    'message' => [
        'close_before_delete' => '请先关闭配置后再执行删除操作',
        'already_enabled' => '当前配置已启用',
        'already_disabled' => '当前配置已停用',
        'config_conflict' => ':network 网络已关联 PSP：:psp，不可配置',
        'enable_conflict' => ':network 网络已关联 PSP：:psp，不可启用',
        'invalid_state' => '路由当前状态不允许此操作，请刷新列表后重试',
        'duplicate_config' => ':config_type（:currency）：:network 网络 :channel 通道配置已存在，不可重复创建',
        'route_conflict' => '同类型、币种下，:network 网络已配置，更换通道请前往已有列表修改',
    ],
];
