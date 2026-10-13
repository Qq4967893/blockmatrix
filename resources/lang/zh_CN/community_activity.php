<?php

return [
    'status' => [
        0 => '待上线',
        1 => '上线',
        2 => '作废',
        'unknown' => '未知',
    ],
    'publish_type' => [
        0 => '定时',
        1 => '人工',
        'unknown' => '未知',
    ],
    'message' => [
        'void_success' => '作废成功',
        'voided_no_action' => '活动已作废，无法再次操作',
        'publish_success' => '发布成功',
        'only_pending_can_publish' => '仅待上线活动可发布',
        'activity_ended' => '活动已结束，无法发布',
    ],
];
