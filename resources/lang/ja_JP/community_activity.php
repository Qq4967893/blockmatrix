<?php

return [
    'status' => [
        0 => '未公開',
        1 => '公開中',
        2 => '無効',
        'unknown' => '不明',
    ],
    'publish_type' => [
        0 => '予約',
        1 => '手動',
        'unknown' => '不明',
    ],
    'message' => [
        'void_success' => '無効化に成功しました',
        'voided_no_action' => 'この活動はすでに無効化されているため、再操作できません。',
        'publish_success' => '公開に成功しました',
        'only_pending_can_publish' => '未公開の活動のみ公開できます。',
        'activity_ended' => 'この活動はすでに終了しているため、公開できません。',
    ],
];
