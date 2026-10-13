<?php

return [
    'status' => [
        0 => '待上線',
        1 => '上線',
        2 => '作廢',
        'unknown' => '未知',
    ],
    'publish_type' => [
        0 => '定時',
        1 => '人工',
        'unknown' => '未知',
    ],
    'message' => [
        'void_success' => '作廢成功',
        'voided_no_action' => '活動已作廢，無法再次操作',
        'publish_success' => '發布成功',
        'only_pending_can_publish' => '僅待上線活動可發布',
        'activity_ended' => '活動已結束，無法發布',
    ],
];
