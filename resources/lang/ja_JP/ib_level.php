<?php

return [
    'message' => [
        'name_exists' => 'IBレベル名は既に存在します。重複項目: :details',
        'threshold_greater_previous' => 'IBレベルのしきい値は前のレベルより大きくする必要があります。前のレベル: active traders=:prev_active_trader_threshold, team position size=:prev_team_position_size_threshold',
        'ib_commission_rule_assigned' => 'このコミッションルールはすでに他のIBレベルに紐付けられており、再度使用できません。',
        'base_commission_rate_conflict' => 'IBレベルに紐付けられたコミッションルールの保底コミッション率が重複しています：:rate',
    ],
    'commission_status' => [
        'assigned' => 'コミッション済み',
        'unassigned' => '未設定',
    ],
    'export' => [
        'ib_applications' => [
            'headers' => ['ユーザーID', '連絡先', '主要取引所', '直近3か月取引量', 'プロモーション内容', '申請日時', '操作担当', 'ステータス', '却下理由'],
        ],
        'ib_accounts' => [
            'headers' => ['ユーザーID', '代理ID', '自己取引コミッション', '代理レベル', '上位代理ID', 'ユーザータイプ', 'チーム人数', 'チーム建玉量', 'コミッションルール', 'コミッション状態', '保護期間状態', '代理開始日時', '更新日時'],
        ],
        'ib_levels' => [
            'headers' => ['レベル順序', 'レベル名', 'コミッションルール', '初期デフォルトレベル', 'アクティブ顧客数しきい値', 'チーム建玉量しきい値', '評価ルール', 'ルールロック', '作成日時', '更新日時'],
        ],
    ],
];
