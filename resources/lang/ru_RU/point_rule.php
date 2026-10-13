<?php

return [
    'status' => [
        -1        => 'Ожидает активации',
        0         => 'Отключено',
        1         => 'Включено',
        'unknown' => 'Неизвестно',
    ],

    'cycle_period' => [
        0         => 'Без ограничений',
        1         => 'Ежедневно',
        2         => 'Еженедельно',
        3         => 'Ежемесячно',
        4         => 'Ежегодно',
        'unknown' => 'Неизвестно',
    ],

    'cycle_limit' => [
        'unique'   => 'Единовременно',
        'loop'     => 'Цикл',
        'limited'  => 'Ограничено по количеству',
        'unknown'  => 'Неизвестно',
    ],

    'event_key' => [
        'registered'               => 'Регистрация завершена',
        'open_app'                 => 'Посещение (Web/H5)',
        'bind_tg'                  => 'Привязать Telegram',
        'bind_line'                => 'Привязать Line',
        'bind_node'                => 'Привязать Node',
        'first_recharge'           => 'Первое пополнение',
        'recharge'                 => 'Разовое пополнение ≥ сумма',
        'first_open_trade'         => 'Первая сделка завершена',
        'open_trade'               => 'Количество сделок ≥ число',
        'login_streak'             => 'Серия входов (дни)',
        'invitee_registered'       => 'Регистрация приглашенного',
        'invitee_first_open_trade' => 'Первая сделка приглашенного',
        'prop_join'                => 'Участвовать в LimitBreak Challenge',
        'submit_survey'            => 'Отправить анкету',
        'prop_share'               => 'Поделиться событием LimitBreak',
        'unknown'                  => 'Неизвестно',
    ],

    'event_param' => [
        'amount'             => 'Сумма',
        'trade_count'        => 'Количество сделок',
        'login_streak_days'  => 'Дни непрерывного входа',
    ],

    'task_type' => [
        0 => 'Все',
        1 => 'Задание новичка',
        2 => 'Торговое задание',
        3 => 'Социальное задание',
    ],
    'points_type' => [
        1 => 'XP',
        2 => 'Drop',
        'unknown' => 'Unknown',
    ],

    'message' => [
        'cannot_change_disabled' => 'Отключенные правила нельзя изменять. Создайте новое правило.',
        'invalid_cycle_period_day' => 'Указано неверное значение периода цикла.',
        'duplicate_trigger_identifier' => 'Идентификатор триггерного события уже существует',
    ],
];
