<?php

return [
    'status' => [
        0         => '已禁用',
        1         => '已启用',
        'unknown' => '未知',
    ],

    'event' => [
        'registered'              => '注册成功',
        'prop_challenge_succeed'  => '挑战成功',
        'unknown'                 => '未知',
    ],

    'message' => [
        'duplicate_name'       => '徽章名称已存在。',
        'duplicate_short_name' => '徽章简称已存在。',
        'duplicate_lang'       => '多语言条目不可重复。',
        'name_required'        => '至少需要填写一个本地化名称。',
        'time_required'        => '请同时提供开始和结束时间。',
        'time_invalid'         => '时间格式无效。',
        'time_future_only'     => '时间窗口必须在未来。',
        'time_sequence'        => '结束时间需晚于开始时间。',
    ],
];
