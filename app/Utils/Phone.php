<?php

namespace App\Utils;

use libphonenumber\PhoneNumberUtil;

class Phone
{
    /**
     * 手机号格式验证
     *
     * @param string $phone 带区号的手机号
     *
     * @return bool
     */
    public static function is(string $phone): bool
    {
        if (empty($phone) || ! str_starts_with($phone, "+")) {
            return false;
        }
        if (str_starts_with($phone, "+86")) {
            $pattern = "/^1\d{10}$/";
            return preg_match($pattern, str_replace("+86", "", $phone)) === 1;
        }
        try {
            $phoneNumberUtil = PhoneNumberUtil::getInstance();
            $instance        = $phoneNumberUtil->parse($phone);
            return $phoneNumberUtil->isValidNumber($instance);
        } catch (\Exception $exception) {
            return false;
        }
    }
}
