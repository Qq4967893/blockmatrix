<?php

use Scaffold\FluxWallet\Facades\FluxWallet;

return [
    'config_type' => [
        FluxWallet::OperationWithdrawal => 'Вывод',
        FluxWallet::OperationDeposit => 'Депозит',
    ],
    'status' => [
        'enabled' => 'Включено',
        'not_enabled' => 'Не включено',
        'disabled' => 'Отключено',
    ],
    'message' => [
        'close_before_delete' => 'Перед удалением отключите конфигурацию.',
        'already_enabled' => 'Конфигурация уже включена.',
        'already_disabled' => 'Конфигурация уже отключена.',
        'config_conflict' => ':network уже связан с PSP: :psp, повторная настройка невозможна.',
        'enable_conflict' => ':network уже связан с PSP: :psp, повторное включение невозможно.',
        'invalid_state' => 'Текущее состояние маршрута не допускает это действие. Обновите список и повторите.',
        'duplicate_config' => 'Конфигурация :config_type для :currency в сети :network (канал :channel) уже существует и не может быть создана повторно.',
        'route_conflict' => 'Конфигурация сети :network с таким же типом и валютой уже существует. Чтобы сменить канал, измените существующую конфигурацию в списке.',
    ],
];
