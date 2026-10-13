<?php

return [
    'boolean' => [
        'yes' => 'はい',
        'no' => 'いいえ',
    ],
    'message' => [
        'max_invest_amount_gte_follower_fund' => 'フォロー可能額はフォロースケール閾値以上である必要があります。',
        'follower_fund_threshold_numeric' => 'フォロースケール閾値は数値である必要があります。',
        'follower_count_threshold_integer' => 'フォロー人数閾値は整数である必要があります。',
        'level_threshold_must_be_greater_than_previous' => ':field は前のレベルより大きい必要があります。',
        'level_threshold_must_be_less_than_next' => ':field は次のレベルより小さい必要があります。',
        'level_has_master_users_cannot_delete' => 'このレベルにはマスターユーザーが存在するため、削除できません。',
        'initial_level_exists' => '初期デフォルトレベルはすでに存在します：:level_code。',
        'not_allow_master_operation' => '現在の状態ではこの操作は許可されていません。',
        'not_allow_level_protection' => '無効、却下、申請中のマスターにはレベル保護を設定できません。',
        'unsupported_target_status' => 'サポートされていないターゲット状態です。',
        'already_public' => 'すでに公開マスターです。',
        'already_private' => 'すでに非公開マスターです。',
        'already_offline' => 'すでに無効です。',
    ],
    'field' => [
        'balance_threshold' => '残高閾値',
        'order_count_30d_threshold' => '過去30日取引回数閾値',
        'profit_rate_30d_threshold' => '過去30日収益率閾値',
    ],
    'master_status' => [
        '0' => '申請中',
        '1' => '正常',
        '2' => '却下',
        '3' => '無効',
        '4' => '非表示',
    ],
    'master_application_status' => [
        '1' => '処理中',
        '2' => '承認',
        '3' => '却下',
    ],
    'master_private' => [
        '0' => '公開表示',
        '1' => '非公開マスター',
    ],
    'master_type' => [
        '0' => '公開マスター',
        '1' => '非公開マスター',
    ],
    'protection_status' => [
        'inactive' => '未開始',
        'active' => '保護中',
        'ended' => '終了済み',
    ],
    'export' => [
        'master_applications' => [
            'headers' => ['取引プラットフォーム', '取引タイプ', '口座情報', 'マスタータイプ', '報酬分配率', '運用規模', '操作者', '申請日時', '審査状態'],
        ],
        'masters' => [
            'headers' => ['マスターID', '取引口座ID', '口座種別', 'マスターレベル', '残高', '過去30日取引回数', '過去30日収益率', 'フォロースケール', 'フォロー人数', '累計分配報酬', '報酬分配率', '現在の非表示日数', '等級保護開始時間', '等級保護終了時間', '保護状態', '理由', '状態'],
        ],
        'commission_records' => [
            'headers' => ['注文ID', 'フォロワーID', 'マスターID', 'マスター取引口座ID', '今回損益', '分配報酬額', '分配報酬率', 'マスターレベル', '注文クローズ時刻', '分配時刻', '状態'],
        ],
        'follow_relations' => [
            'headers' => ['マスターID', 'フォロワーID', 'マスター取引口座ID', 'フォロワー取引口座ID', 'マスター取引口座種別', 'フォロワー取引口座種別', 'フォロー金額', 'フォロー金額（損益込み）', 'マスターレベル', 'コピー開始時刻', 'コピー終了時刻', '状態'],
        ],
        'potential_masters' => [
            'headers' => ['ユーザーID', '取引口座ID', '口座種別', '特別顧客', '残高', '過去30日取引回数', '過去30日収益率'],
        ],
    ],
    'follow_status' => [
        'following' => 'フォロー中',
        'ended' => '終了済み',
    ],
    'commission_status' => [
        'pending' => '未分配',
        'settled' => '分配済み',
        'unknown' => '不明',
    ],
];
