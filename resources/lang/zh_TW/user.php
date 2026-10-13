<?php

return [

    'message' => [
        'not_empty' => '使用者不能為空',
        'wrong_options' => '選項錯誤',
        'account_error' => '帳號填寫錯誤',
        'not_exist' => '客戶不存在',
        'transfer_not_allowed' => '使用者不顯示在列表內，不可劃轉',
        'least_one' => '請至少填寫一項資訊',
        'phone_not_exist' => '手機號不存在',
        'unlocking_failed' => '解鎖失敗',
        'select_file' => '請選擇要上傳的檔案',
        'transfer_is_not_allowed' => [
            0 => '該客戶是一級代理商，不允許劃轉',
            1 => '目標上級代理非代理商，不允許劃轉',
            2 => '該代理商和目標代理商在同一條鏈上，不允許從高級向低級劃轉',
            3 => '該代理商不在代理層級路徑內',
            4 => '代理層級不以對比層級為開頭',
        ],
    ],

    // 站内信
    'sys_message' => [
        'type_err' => '消息类型不存在',
        'message_empty' => '标题和内容不能为空',
        'type_empty' => '消息类型不能为空',
        'user_not_empty' => '用户不能为空',
        'area_required' => '请选择大区',
        'limit_exceeded' => '账号数量不超过 10 个',
        'format_err' => '消息格式错误',
    ],

    // 账号类型
    'user_type' => [
        0 => '',
        1 => '白名單',
        2 => '黑名單',
    ],

    'white' => [
        'already' => '用户已在白名单中',
        'not_in_list' => '用户不在白名单中',
    ],

    'black' => [
        'already_black' => '用户已在黑名单中',
    ],

    // 仅注册
    'status_registered' => '已注册',

    // 是否
    'boolean' => [
        'yes' => '是',
        'no' => '否',
    ],

    'validation_status' => [
        1 => '正常',
        'unknown' => '未知',
    ],

    'bonus_status' => [
        1 => '可用',
        0 => '不可用',
    ],

    // 是否入金
    'is_pay' => [
        0 => '否',
        1 => '是',
    ],

    // 注册方式
    'reg_way' => [
        'email' => '邮箱注册',
        'wallet' => '钱包注册',
        'unknown' => '注册方式未知',
    ],

    // 登入方式
    'login_type' => [
        'email' => '郵箱登入',
        'wallet' => '錢包登入',
        'line' => 'LINE 登入',
        'telegram' => 'Telegram 登入',
        'apple' => 'Apple 登入',
        'google' => 'Google 登入',
    ],

    // 真实账户
    'live_account' => '真实账户',

    // 账户类型-标准账户
    'standard_account' => '标准账户',

    // 邮箱验证状态
    'email_status' => [
        'unverified' => '否',
        'verified' => '是',
    ],

    // 客户消息类型
    'msg_type' => [
        'all' => '全部客户',
        'select' => '指定客户',
    ],

    // 账户类型
    'non_test_account' => '非测试账户',
    'test_account' => '测试账户',

    'trade_account' => [
        'so_type' => [
            1 => '全仓',
            2 => '逐仓',
            'default' => '全仓',
        ],
        'position_mode' => [
            0 => '不合併',
            1 => '單向',
            2 => '雙向',
        ],
    ],

    'export' => [
        'headers' => ['使用者ID', '暱稱', '郵箱', '類型', '上級代理ID', '註冊方式', '網路', '錢包地址', '是否充值', '註冊時間'],
        'login_logs' => [
            'headers' => ['日誌ID', '請求ID', '使用者ID', '是否最新登入', 'IP', '國家', 'User Agent', '裝置 UUID', '用戶端時區', 'Canvas 指紋 Hash', 'Audio 指紋 Hash', '螢幕解析度', '裝置綜合特徵 Hash', '登入方式', '登入時間'],
        ],
        'white_list' => [
            'headers' => ['使用者ID', '暱稱', '類型', '郵箱', '註冊時間', '操作人', '加入白名單時間', '註冊方式', '是否充值', '錢包地址'],
        ],
        'black_list' => [
            'headers' => ['使用者ID', '註冊時間', '錢包地址', '郵箱', '操作人', '加入黑名單時間', '原因', '限制權限', '是否充值', '註冊方式'],
        ],
    ],
];
