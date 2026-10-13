<?php

return [
    'status' => [
        -1        => '有効化待ち',
        0         => '無効',
        1         => '有効',
        'unknown' => '不明',
    ],

    'cycle_period' => [
        0         => '無制限',
        1         => '日ごと',
        2         => '週ごと',
        3         => '月ごと',
        4         => '年ごと',
        'unknown' => '不明',
    ],

    'cycle_limit' => [
        'unique'   => '一回限り',
        'loop'     => '繰り返し',
        'limited'  => '回数制限',
        'unknown'  => '不明',
    ],

    'event_key' => [
        'registered'               => '登録完了',
        'open_app'                 => 'アクセス（Web/H5）',
        'bind_tg'                  => 'Telegramを連携',
        'bind_line'                => 'Lineを連携',
        'bind_node'                => 'Nodeを連携',
        'first_recharge'           => '初回入金',
        'recharge'                 => '単回入金≥金額',
        'first_open_trade'         => '初回取引完了',
        'open_trade'               => '取引件数≥回数',
        'login_streak'             => '連続ログイン（日）',
        'invitee_registered'       => '招待者が登録',
        'invitee_first_open_trade' => '招待者の初回取引',
        'prop_join'                => 'LimitBreak Challengeに参加',
        'submit_survey'            => 'アンケート送信',
        'prop_share'               => 'PROPイベントを共有',
        'unknown'                  => '不明',
    ],

    'event_param' => [
        'amount'             => '金額',
        'trade_count'        => '取引回数',
        'login_streak_days'  => '連続ログイン日数',
    ],

    'task_type' => [
        0 => 'すべて',
        1 => '初心者タスク',
        2 => '取引タスク',
        3 => 'ソーシャルタスク',
    ],
    'points_type' => [
        1 => 'XP',
        2 => 'Drop',
        'unknown' => 'Unknown',
    ],

    'message' => [
        'cannot_change_disabled' => '無効化されたルールは変更できません。新しいルールを作成してください。',
        'invalid_cycle_period_day' => '循環周期の指定値が正しくありません。',
        'duplicate_trigger_identifier' => 'このトリガーイベントの識別子は既に存在します',
    ],
];
