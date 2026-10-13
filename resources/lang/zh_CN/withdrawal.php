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
        'title_risk' => ['用户ID', '出金来源', '出金去向', '订单号', '三方订单号', '出金通道', '出金币种', '出金金额', '当日提现次数', '当日总提现金额', '实际到账币种', '实际到账', '手续费', '系统汇率', '出金时间(GMT+0)', '到账时间', '哈希值', '网络', '邮箱地址', '客户类型', '风控状态', '操作'],
        'title_finance' => ['用户ID', '出金来源', '出金去向', '订单号', '三方订单号', '出金通道', '出金币种', '出金金额', '实际到账币种', '实际到账', '手续费', '系统汇率', '出金时间(GMT+0)', '哈希值', '网络', '邮箱地址', '客户类型', '财务状态', '操作'],
    ],
    'message' => [
        'param_error'  => '出金参数错误',
        'format_error' => '出金参数格式错误',
        'has_withdrawal_restriction' => '该用户存在出金限制，无法审核通过',
        'risk_already_approved_finance_failed' => '该订单风控已通过且财务已失败，请在财务审核中处理',
        'retry_in_progress' => '钱包出金重试正在处理中，请等待本次重试完成',
    ],
];
