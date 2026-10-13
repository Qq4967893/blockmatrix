<?php

return [
    'app'       => [
        'env' => env('APP_ENV'),
    ],

    //Redis缓存
    'redis'     => [
        'captcha'        => 'blockmatrix:auth:captcha:',
    ],

    //验证码校验
    'captcha'   => [
        'none' => env('NO_CAPTCHA'),
    ],

    //特殊角色
    'role'      => [
        'root' => env('ROOT_ROLES'),
    ],

    //邮件
    'mail'      => [
        'exception'     => env('EXCEPTION_EMAIL'),
        'compliance'    => env('REPORT_EMAIL'),
        'media'         => env('MEDIA_EMAIL'),
        'trade_contest' => env('CONTEST_EMAIL'),
        'invite_friend' => env('FRIEND_EMAIL'),
    ],
];
