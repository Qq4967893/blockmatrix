<?php

namespace App\Utils;

use Illuminate\Support\Arr;

class Util
{
    /**
     * 生成随机密码
     *
     * @param int $count 密码位数
     *
     * @return string
     * @throws \Exception
     */
    public static function generatePassword($count)
    {
        $numberCount = random_int(1, $count - 2);
        $lowerCount  = random_int(1, $count - $numberCount - 1);
        $upperCount  = $count - $numberCount - $lowerCount;

        $numberSet = self::randomIntSet($numberCount, 0, 9);
        $lowerSet  = self::randomIntSet($lowerCount, 10, 35);
        $upperSet  = self::randomIntSet($upperCount, 36, 61);

        $set = array_merge($numberSet, $lowerSet, $upperSet);

        return implode('', Arr::shuffle($set));
    }

    /**
     * 生成随机密码
     *
     * @param int $length 长度
     * @param int $min    最小值
     * @param int $max    最大值
     *
     * @return array
     * @throws \Exception
     */
    private static function randomIntSet($length, $min, $max)
    {
        $pattern = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $set     = [];
        for ($i = 0; $i < $length; $i++) {
            $c     = random_int($min, $max);
            $set[] = $pattern[$c];
        }
        return $set;
    }

    /**
     * 替换本地image标签内容
     *
     * @param string $html html内容
     * @param string $url  域名地址
     *
     * @return string
     */
    public static function htmlImageSrc(string $html, string $url): string
    {
        return preg_replace_callback(
            '/<img\s[^>]*src\s*=\s*["\']([^"\']+)["\'][^>]*>/i',
            function ($matches) use ($url) {
                $src = $matches[1];
                if (preg_match('#^https?://#i', $src) || strpos($src, '//') === 0) {
                    return $matches[0];
                }
                $newSrc = rtrim($url, '/') . '/' . ltrim($src, '/');
                return str_replace($src, $newSrc, $matches[0]);
            },
            $html
        );
    }

    /**
     * 统一金额输出格式：有小数保留有效小数，无小数返回整数样式
     *
     * @param mixed $value 金额值
     *
     * @return string
     */
    public static function normalizeDecimalOutput(mixed $value): string
    {
        $stringValue = (string) $value;
        if (!str_contains($stringValue, '.')) {
            return $stringValue;
        }

        $normalizedValue = rtrim(rtrim($stringValue, '0'), '.');

        return $normalizedValue === '' ? '0' : $normalizedValue;
    }
}
