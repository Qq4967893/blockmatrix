<?php

return [
    'type' => [
        'deposit'             => 'Deposit',
        'withdrawal'          => 'Withdrawal',
        'withdrawal_return'   => 'Withdrawal Return',
        'referral'            => 'Referral Commission',
        'loss_compensation'   => 'Loss Compensation',
        'ftmo_cashback'       => 'LimitBreak Cashback',
        'ftmo_payout'         => 'LimitBreak Payout',
        'unknown'             => 'Others',
    ],
    'status' => [
        0 => 'Processing',
        1 => 'Succeeded',
        2 => 'Failed',
    ],
    'risk_status' => [
        'pending'  => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'canceled' => 'Canceled',
    ],
    'finance_status' => [
        'pending'    => 'Pending',
        'approved'   => 'Approved',
        'success'    => 'Succeeded',
        'failed'     => 'Failed',
        'rejected'   => 'Rejected',
        'processing' => 'Processing',
    ],
    'placeholder' => [
        'from'     => '-',
        'to'       => '-',
        'network'  => '-',
        'tx_hash'  => '-',
    ],
    'export' => [
        'title_risk' => ['User ID', 'From', 'To', 'Order No.', 'Third-party Order No.', 'Withdrawal Channel', 'Withdrawal Currency', 'Withdrawal Amount', 'Daily Withdrawal Count', 'Daily Total Withdrawal Amount', 'Actual Arrival Currency', 'Actual Arrival Amount', 'Fee', 'System Rate', 'Withdrawal Time (GMT+0)', 'Arrival Time', 'Tx Hash', 'Network', 'Email', 'Customer Type', 'Risk Status', 'Operator'],
        'title_finance' => ['User ID', 'From', 'To', 'Order No.', 'Third-party Order No.', 'Withdrawal Channel', 'Withdrawal Currency', 'Withdrawal Amount', 'Actual Arrival Currency', 'Actual Arrival Amount', 'Fee', 'System Rate', 'Withdrawal Time (GMT+0)', 'Tx Hash', 'Network', 'Email', 'Customer Type', 'Finance Status', 'Operator'],
    ],
    'message' => [
        'param_error'  => 'Invalid withdrawal parameters',
        'format_error' => 'Invalid withdrawal parameter format',
        'has_withdrawal_restriction' => 'This user has withdrawal restrictions and cannot be approved',
        'risk_already_approved_finance_failed' => 'This order has passed risk review and failed finance processing. Please handle it in finance review.',
        'retry_in_progress' => 'A wallet withdrawal retry is already in progress. Please wait for it to finish.',
    ],
];
