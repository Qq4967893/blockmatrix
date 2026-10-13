<?php

namespace App\Utils;

class Email
{
    public static function s3ImageAddress()
    {
        return config('app.env') === 'pro'
            ? "https://ginfx-prod.s3.ap-southeast-1.amazonaws.com"
            : "https://mangox-inc.s3.ap-northeast-1.amazonaws.com";
    }
    /**
     * 邮件格式验证
     *
     * @param string $email 邮箱
     *
     * @return bool
     */
    public static function is($email)
    {
        if (empty($email) || ! is_string($email)) {
            return false;
        }
        //邮箱前缀由英文数字、下划线、减号、点组成，以英文数字结尾
        $pattern = '/^\w+([-.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/';
        if (preg_match($pattern, $email)) {
            return true;
        }
        return false;
    }
}
