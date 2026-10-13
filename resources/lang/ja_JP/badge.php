<?php

return [
    'status' => [
        0         => '無効',
        1         => '有効',
        'unknown' => '不明',
    ],

    'event' => [
        'registered'              => '登録成功',
        'ftmo_challenge_success'  => 'LimitBreakチャレンジ成功',
        'unknown'                 => '不明',
    ],

    'message' => [
        'duplicate_name'       => 'バッジ名は既に存在します。',
        'duplicate_short_name' => 'バッジ略称は既に存在します。',
        'duplicate_lang'       => '多言語エントリは重複できません。',
        'name_required'        => '少なくとも1つのローカライズ名が必要です。',
        'time_required'        => '開始時刻と終了時刻を同時に提供してください。',
        'time_invalid'         => '時刻の形式が無効です。',
        'time_future_only'     => '時間ウィンドウは未来である必要があります。',
        'time_sequence'        => '終了時刻は開始時刻より後でなければなりません。',
    ],
];
