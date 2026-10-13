<?php

return [

    'message' => [
        'not_empty' => '用户不能为空',
        'wrong_options' => '选项错误',
        'account_error' => '账号填写错误',
        'not_exist' => '客户不存在',
        'transfer_not_allowed' => '用户不显示在内，不可划转',
        'least_one' => '请至少填写一项信息',
        'phone_not_exist' => '手机号不存在',
        'unlocking_failed' => '解锁失败',
        'select_file' => '请选择要上传的文件',
        'transfer_is_not_allowed' => [
            0 => '该客户是一级代理商，不允许划转',
            1 => '目标上级代理非代理商，不允许划转',
            2 => '该代理商和目标代理商在同一个链上，不允许从高级向低级划转',
            3 => '该代理商不在代理层级路径内',
            4 => '代理层级不以对比层级为开头',
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
        1 => '白名单',
        2 => '黑名单',
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

    // 登录方式
    'login_type' => [
        'email' => '邮箱登录',
        'wallet' => '钱包登录',
        'line' => 'LINE 登录',
        'telegram' => 'Telegram 登录',
        'apple' => 'Apple 登录',
        'google' => 'Google 登录',
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
            0 => '不合并',
            1 => '单向',
            2 => '双向',
        ],
    ],

    'export' => [
        'headers' => ['用户ID', '昵称', '邮箱', '类型', '上级代理ID', '注册方式', '网络', '钱包地址', '是否充值', '注册时间'],
        'login_logs' => [
            'headers' => ['日志ID', '请求ID', '用户ID', '是否最新登录', 'IP', '国家', 'User Agent', '设备UUID', '客户端时区', 'Canvas指纹Hash', 'Audio指纹Hash', '屏幕分辨率', '设备综合特征Hash', '登录方式', '登录时间'],
        ],
        'white_list' => [
            'headers' => ['用户ID', '昵称', '类型', '邮箱', '注册时间', '操作人', '加入白名单时间', '注册方式', '是否充值', '钱包地址'],
        ],
        'black_list' => [
            'headers' => ['用户ID', '注册时间', '钱包地址', '邮箱', '操作人', '加入黑名单时间', '原因', '限制权限', '是否充值', '注册方式'],
        ],
    ],
];
