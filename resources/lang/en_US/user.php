<?php

return [

    'message' => [
        'not_empty' => 'User cannot be empty',
        'wrong_options' => 'Invalid option',
        'account_error' => 'Account information is incomplete',
        'not_exist' => 'Client does not exist',
        'least_one' => 'You need to fill in at least one field', // 至少填写一项
        'phone_not_exist' => 'Phone number does not exist', // 手机号不存在
        'unlocking_failed' => 'Unlocking failed', // 解锁失败
        'select_file' => 'Please select the file to upload',
    ],

    // 站内信
    'sys_message' => [
        'type_err' => 'The message type does not exist',
        'message_empty' => 'The title and content cannot be empty',
        'type_empty' => 'Message type cannot be empty',
        'user_not_empty' => 'User cannot be empty',
        'area_required' => 'Please select an area',
        'limit_exceeded' => 'No more than 10 accounts',
        'format_err' => 'Invalid message format',
    ],

    // 账号类型
    'user_type' => [
        0 => '',
        1 => 'Whitelist',
        2 => 'Blacklist',
    ],

    'white' => [
        'already' => 'User already exists in whitelist',
        'not_in_list' => 'User is not in whitelist',
    ],

    'black' => [
        'already_black' => 'User is already in blacklist',
    ],

    // 仅注册
    'status_registered' => 'Registered',

    // 是否
    'boolean' => [
        'yes' => 'Yes',
        'no' => 'No',
    ],

    'validation_status' => [
        1 => 'Normal',
        'unknown' => 'Unknown',
    ],

    'bonus_status' => [
        1 => 'Available',
        0 => 'Unavailable',
    ],

    // 是否入金
    'is_pay' => [
        0 => 'no',
        1 => 'yes',
    ],

    // 注册方式
    'reg_way' => [
        'email' => 'Email registration',
        'wallet' => 'Wallet registration',
        'unknown' => 'Unknown registration method',
    ],

    // 登录方式
    'login_type' => [
        'email' => 'Email Login',
        'wallet' => 'Wallet Login',
        'line' => 'LINE Login',
        'telegram' => 'Telegram Login',
        'apple' => 'Apple Login',
        'google' => 'Google Login',
    ],

    // 真实账户
    'live_account' => 'Live account',

    // 账户类型-标准账户
    'standard_account' => 'Standard account',

    // 邮箱验证状态
    'email_status' => [
        'unverified' => 'No',
        'verified' => 'Yes',
    ],

    // 客户消息类型
    'msg_type' => [
        'all' => 'All Clients',
        'select' => 'Selected Client',
    ],

    // 账户类型
    'non_test_account' => 'Non-test account',
    'test_account' => 'Test account',

    'trade_account' => [
        'so_type' => [
            1 => 'Cross margin',
            2 => 'Isolated margin',
            'default' => 'Cross margin',
        ],
        'position_mode' => [
            0 => 'No Merge',
            1 => 'Netting',
            2 => 'Hedge',
        ],
    ],

    'export' => [
        'headers' => ['User Code', 'Nickname', 'Email', 'Type', 'Parent IB ID', 'Register Way', 'Network', 'Wallet Address', 'Has Deposit', 'Register Time'],
        'login_logs' => [
            'headers' => ['Log ID', 'Request ID', 'User Code', 'Latest Login', 'IP', 'Country', 'User Agent', 'Device UUID', 'Client Timezone', 'Canvas Fingerprint Hash', 'Audio Fingerprint Hash', 'Screen Resolution', 'Device Fingerprint Hash', 'Login Type', 'Login Time'],
        ],
        'white_list' => [
            'headers' => ['User Code', 'Nickname', 'Type', 'Email', 'Register Time', 'Operator', 'Whitelist Added Time', 'Register Way', 'Has Deposit', 'Wallet Address'],
        ],
        'black_list' => [
            'headers' => ['User Code', 'Register Time', 'Wallet Address', 'Email', 'Operator', 'Blacklist Added Time', 'Reason', 'Restricted Privileges', 'Has Deposit', 'Register Way'],
        ],
    ],
];
