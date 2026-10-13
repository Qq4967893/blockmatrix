<?php

return [
    'operation_source' => [
        1 => '系統',
        2 => '人工',
    ],

    'direction' => [
        1 => '發放',
        -1 => '扣除',
    ],

    'management_type' => [
        9 => '穿倉補償',
        13 => '贈金',
        14 => '紅包',
    ],

    'message' => [
        'single_failed' => '處理失敗：:details',
        'batch_failed' => '批次處理完成，部分用戶處理失敗：:details',
        'failed_item' => ':user_code: :reason',
        'failed_separator' => '；',
    ],

    'export' => [
        'title_management' => ['用戶ID', '操作來源', '方向', '資金類型', '生成時間(GMT+0)'],
    ],
];
