<?php

return [
    'operation_source' => [
        1 => 'システム',
        2 => '手動',
    ],

    'direction' => [
        1 => '付与',
        -1 => '控除',
    ],

    'management_type' => [
        9 => 'マイナス残高補填',
        13 => 'ボーナス',
        14 => 'お年玉',
    ],

    'message' => [
        'single_failed' => '処理に失敗しました: :details',
        'batch_failed' => '一括処理が完了しましたが、一部のユーザーの処理に失敗しました: :details',
        'failed_item' => ':user_code: :reason',
        'failed_separator' => '；',
    ],

    'export' => [
        'title_management' => ['ユーザーID', '操作元', '入出金方向', '資金タイプ', '作成時間(GMT+0)'],
    ],
];
