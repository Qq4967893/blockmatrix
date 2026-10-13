<?php

return [
    'operation_source' => [
        1 => '系统',
        2 => '人工',
    ],

    'direction' => [
        1 => '发放',
        -1 => '扣除',
    ],

    'management_type' => [
        9 => '穿仓补偿',
        13 => '赠金',
        14 => '红包',
    ],

    'message' => [
        'single_failed' => '处理失败：:details',
        'batch_failed' => '批次处理完成，部分用户处理失败：:details',
        'failed_item' => ':user_code: :reason',
        'failed_separator' => '；',
    ],

    'export' => [
        'title_management' => ['用户ID', '操作来源', '方向', '资金类型', '生成时间(GMT+0)'],
    ],
];
