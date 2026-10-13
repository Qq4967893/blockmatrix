<?php

return [
    'type' => [
        'deposit'             => '入金',
        'withdrawal'          => '出金',
        'withdrawal_return'   => '出金返還',
        'referral'            => '紹介返佣',
        'loss_compensation'   => '損失補償',
        'ftmo_cashback'       => 'LimitBreakキャッシュバック',
        'ftmo_payout'         => 'LimitBreak支出',
        'unknown'             => 'その他',
    ],
    'status' => [
        0 => '処理中',
        1 => '成功',
        2 => '失敗',
    ],
    'risk_status' => [
        'pending'  => '審査待ち',
        'approved' => '承認済み',
        'rejected' => '却下',
        'canceled' => '取消',
    ],
    'finance_status' => [
        'pending'    => '審査待ち',
        'approved'   => '承認済み',
        'success'    => '成功',
        'failed'     => '失敗',
        'rejected'   => '却下',
        'processing' => '処理中',
    ],
    'placeholder' => [
        'from'     => '-',
        'to'       => '-',
        'network'  => '-',
        'tx_hash'  => '-',
    ],
    'export' => [
        'title_risk' => ['User ID', '出金元', '出金先', '注文番号', '三者注文番号', '出金チャネル', '出金通貨', '出金額', '当日の出金回数', '当日の出金総額', '実際着金通貨', '実際着金額', '手数料', 'システムレート', '出金時間(GMT+0)', '着金時間', 'ハッシュ', 'ネットワーク', 'メールアドレス', '顧客タイプ', 'リスクステータス', '操作'],
        'title_finance' => ['User ID', '出金元', '出金先', '注文番号', '三者注文番号', '出金チャネル', '出金通貨', '出金額', '実際着金通貨', '実際着金額', '手数料', 'システムレート', '出金時間(GMT+0)', 'ハッシュ', 'ネットワーク', 'メールアドレス', '顧客タイプ', '財務ステータス', '操作'],
    ],
    'message' => [
        'param_error'  => '出金パラメータが正しくありません',
        'format_error' => '出金パラメータの形式が正しくありません',
        'has_withdrawal_restriction' => '当該ユーザーには出金制限があるため、審査を通過できません',
        'risk_already_approved_finance_failed' => 'この注文はリスク審査承認済みかつ財務処理失敗です。財務審査で処理してください。',
        'retry_in_progress' => 'ウォレット出金のリトライは既に実行中です。完了までお待ちください。',
    ],
];
