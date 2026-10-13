<?php

use Scaffold\FluxWallet\Facades\FluxWallet;

return [
    'config_type' => [
        FluxWallet::OperationWithdrawal => '出金',
        FluxWallet::OperationDeposit => '入金',
    ],
    'status' => [
        'enabled' => '有効',
        'not_enabled' => '未有効',
        'disabled' => '無効',
    ],
    'message' => [
        'close_before_delete' => '削除する前に設定を無効にしてください。',
        'already_enabled' => '現在の設定はすでに有効です。',
        'already_disabled' => '現在の設定はすでに無効です。',
        'config_conflict' => ':network はすでに PSP：:psp に関連付けられているため、重複して設定できません。',
        'enable_conflict' => ':network はすでに PSP：:psp に関連付けられているため、重複して有効化できません。',
        'invalid_state' => 'ルートの現在の状態ではこの操作はできません。一覧を更新して再試行してください。',
        'duplicate_config' => ':config_type（:currency・:network ネットワーク・:channel チャネル）の設定は既に存在するため、重複して作成できません。',
        'route_conflict' => '同じ種類・通貨の :network ネットワークの設定が既に存在します。チャネルを変更する場合は、既存の設定を編集してください。',
    ],
];
