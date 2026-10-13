<?php

return [
    'name_exists' => 'IB手数料ルール名は既に存在します。重複項目: :details',
    'base_commission_rate_exists' => '保底手数料率は既に存在します。:name 現在の率 :rate%',
    'missing_listed_sources' => '一部の上場銘柄が設定されていません',
    'review_status' => [
        0 => '下書き',
        1 => '審査待ち',
        2 => '審査承認済み',
        3 => '却下済み',
        4 => '停止済み',
    ],
    'effective_status' => [
        0 => '未発効',
        1 => '発効済み',
    ],
];
