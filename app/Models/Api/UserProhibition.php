<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Api\UserProhibition as ScaffoldUserProhibition;

class UserProhibition extends ScaffoldUserProhibition
{
    /**
     * 获取用户限制类型映射（已翻译）。
     *
     * @return array<int, string>
     */
    public static function types(): array
    {
        return (array) trans('public.user_prohibition_types');
    }

    /**
     * 获取可用限制类型编码。
     *
     * @return array<int, int>
     */
    public static function typeKeys(): array
    {
        return array_keys(self::types());
    }
}
