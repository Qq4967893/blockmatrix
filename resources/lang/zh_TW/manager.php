<?php

return [

    'message' => [
        'mobile_err'         => '手机号格式错误',
        'email_err'          => '邮箱格式错误',
        'account_used'       => '关联的用户账号已被使用',
        'user_not_exist'     => '用户不存在',
        'superior_not_exist' => '上级不存在',
    ],

    //用户
    'user'    => [
        'multi_field_not_empty' => '真实姓名、密码、角色不能为空',
        'name_exists'           => '登入帳號已存在',
        'email_exist'           => '邮箱已存在',
        'phone_exist'           => '手机号已存在',
        'email_error'           => '邮箱格式错误',
        'phone_error'           => '手机号格式错误',
        'email_empty'           => '邮箱不能为空',
        'phone_empty'           => '手机号不能为空',
        'least_one'             => '请至少填写一项',
        'original_password_err' => '原密码输入错误',
    ],

    //角色菜单
    'role'    => [
        'name_not_empty'       => '角色名称不能为空',
        'name_too_lang'        => '角色名称过长',
        'name_exist'           => '角色名称已存在',
        'superior_not_exist'   => '上级不存在',
        'permission_err'       => '权限格式错误',
        'permission_not_empty' => '数据权限不能为空',
        'non_superiors'        => '上级不存在',
        'unable_delete'        => '该角色下有用户，无法删除',
        'not_empty'            => '角色不能为空',
    ],

    //菜单
    'menu'    => [
        'not_empty' => '菜单不能为空',
    ],

    'password' => [
        'not_empty'  => '密码不能为空',
        'consistent' => '新密码需一致',
        'err'        => '当前密码不正确',
        'short'      => '密码长度至少 6 位',
    ],

    'no_manager'     => '无管理员',

    'validate' => [
        'validation' => '校验错误提示',
        'password'   => '密码需包含大小写字母和数字，长度 8-24 位',
    ],
];
