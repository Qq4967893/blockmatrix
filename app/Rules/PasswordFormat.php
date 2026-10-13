<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class PasswordFormat implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->verify($value);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return trans('manager.validate.password');
    }

    /**
     * 密码强度
     *
     * @param string $str 密码
     *
     * @return bool
     */
    private function verify($str)
    {
        // 检查字符串是否为空或包含空格
        if (empty($str) || str_contains($str, ' ')) {
            return false;
        }
        $len    = strlen($str);
        $lenMin = 8;
        $lenMax = 24;
        // 检查密码长度
        if ($len < $lenMin || $len > $lenMax) {
            return false;
        }
        // 使用正则表达式匹配密码是否同时包含大小写字母和数字
        return (bool) preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $str);
    }
}
