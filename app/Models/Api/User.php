<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Api\User as ScaffoldUser;

class User extends ScaffoldUser
{
    protected $hidden = ['access_token', 'password'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public const VALIDATION_STATUS_NORMAL = 1;

    public const REGISTRATION_WAY_EMAIL = 'email';
    public const REGISTRATION_WAY_WALLET = 'wallet';

    //账户类型
    public const TYPE_REAL     = 0; //真实账户
    public const TYPE_TEST     = 1; //测试账号
    public const TYPE_BLACK    = 2; //黑名单账户

    //入金状态筛选
    public const PAY_STATUS_ALL    = 0;
    public const PAY_STATUS_PAID   = 1;
    public const PAY_STATUS_UNPAID = 2;

    public static function resolveRegistrationWayKey(array $user): ?string
    {
        $email = trim((string) ($user['email'] ?? ''));
        if ($email !== '') {
            return self::REGISTRATION_WAY_EMAIL;
        }

        $walletAddress = trim((string) ($user['wallet_address'] ?? ''));
        if ($walletAddress !== '') {
            return self::REGISTRATION_WAY_WALLET;
        }

        return null;
    }

    public static function resolveRegistrationWayLabel(array $user): string
    {
        $key = self::resolveRegistrationWayKey($user);

        return $key === null ? '-' : trans('user.reg_way.' . $key);
    }

    public static function validationStatusLabel(int $status): string
    {
        $key = sprintf('user.validation_status.%s', $status);
        $translation = trans($key);

        return $translation === $key ? trans('user.validation_status.unknown') : $translation;
    }
}
