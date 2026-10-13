<?php

return [

    'message' => [
        'mobile_err'         => 'Invalid mobile number format',
        'email_err'          => 'Invalid email format',
        'account_used'       => 'The linked pass account has already been used',
        'user_not_exist'     => 'User does not exist',
        'superior_not_exist' => 'Superior does not exist',
    ],

    //用户
    'user'    => [
        'multi_field_not_empty' => 'Real name, password, and role cannot be empty',
        'name_exists'           => 'Real name already exists',
        'email_exist'           => 'Email already exists',
        'phone_exist'           => 'The phone number already exists',
        'email_error'           => 'Invalid email format',
        'phone_error'           => 'Invalid mobile number format',
        'email_empty'           => 'Email cannot be empty',
        'phone_empty'           => 'Phone cannot be empty',
        'least_one'             => 'Please fill in at least one field',
        'original_password_err' => 'Original password is incorrect',
    ],

    //角色菜单
    'role'    => [
        'name_not_empty'       => 'Role name cannot be empty',
        'name_too_lang'        => 'Role name is too long',
        'name_exist'           => 'The role name already exists',
        'superior_not_exist'   => 'Superior does not exist',
        'permission_err'       => 'Invalid permission format',
        'permission_not_empty' => 'Data permission cannot be empty',
        'non_superiors'        => 'Superior does not exist', //上级不存在
        'unable_delete'        => 'There are users under this role and cannot be deleted', //该角色下有用户，无法删除
        'not_empty'            => 'Role cannot be empty',
    ],

    //菜单
    'menu'    => [
        'not_empty' => 'Menu cannot be empty',
    ],

    'password' => [
        'not_empty'  => 'The password must not be empty',
        'consistent' => 'The new password must be consistent',
        'err'        => 'The current password is incorrect',
        'short'      => 'The password must be at least 6 characters long',
    ],

    'no_manager'     => 'No manager',

    'validate' => [
        'validation' => 'The validation error message',
        'password' => 'The password must contain uppercase letters, lowercase letters, and numbers, and be between 8 and 24 characters long',
    ],
];
