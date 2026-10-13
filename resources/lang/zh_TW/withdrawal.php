<?php

return [
    'type' => [
        'deposit'             => '充值',
        'withdrawal'          => '提现',
        'withdrawal_return'   => '提现返还',
        'referral'            => 'Referral返佣',
        'loss_compensation'   => '穿仓补偿',
        'ftmo_cashback'       => 'LimitBreak返现',
        'ftmo_payout'         => 'LimitBreak支出',
        'unknown'             => '其他',
    ],
    'status' => [
        0 => '处理中',
        1 => '成功',
        2 => '失败',
    ],
    'risk_status' => [
        'pending'  => '待审核',
        'approved' => '已通过',
        'rejected' => '已驳回',
        'canceled' => '已撤销',
    ],
    'finance_status' => [
        'pending'    => '待审核',
        'approved'   => '已通过',
        'success'    => '成功',
        'failed'     => '失败',
        'rejected'   => '已驳回',
        'processing' => '处理中',
    ],
    'placeholder' => [
        'from'     => '-',
        'to'       => '-',
        'network'  => '-',
        'tx_hash'  => '-',
    ],
    'export' => [
        'title_risk' => ['用戶ID', '出金來源', '出金去向', '訂單號', '三方訂單號', '出金通道', '出金幣種', '出金金額', '當日提現次數', '當日總提現金額', '實際到賬幣種', '實際到賬', '手續費', '系統匯率', '出金時間(GMT+0)', '到賬時間', '哈希值', '網絡', '郵箱地址', '客戶類型', '風控狀態', '操作'],
        'title_finance' => ['用戶ID', '出金來源', '出金去向', '訂單號', '三方訂單號', '出金通道', '出金幣種', '出金金額', '實際到賬幣種', '實際到賬', '手續費', '系統匯率', '出金時間(GMT+0)', '哈希值', '網絡', '郵箱地址', '客戶類型', '財務狀態', '操作'],
    ],
    'message' => [
        'param_error'  => '出金参数错误',
        'format_error' => '出金参数格式错误',
        'has_withdrawal_restriction' => '该用户存在出金限制，无法审核通过',
        'risk_already_approved_finance_failed' => '該訂單風控已通過且財務已失敗，請在財務審核中處理',
        'retry_in_progress' => '錢包出金重試正在處理中，請等待本次重試完成',
    ],
];
