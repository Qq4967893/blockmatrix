<?php

namespace App\Utils;

use Gregwar\Captcha\CaptchaBuilder;
use Gregwar\Captcha\PhraseBuilder;

class CaptchaUtil
{
    private const EXPIRE_SECONDS = 60;
    private const WIDTH = 126;
    private const HEIGHT = 42;

    /**
     * 生成验证码
     *
     * @param string $key 验证码key
     *
     * @return array<string, int|string>
     */
    public static function captcha(string $key): array
    {
        $phrase = new PhraseBuilder();
        $code = $phrase->build(4);
        $image = self::supportsFreeType()
            ? self::buildTtfCaptcha($code, $phrase)
            : self::buildGdCaptcha($code);

        $phrase = strtolower($code);
        $redis  = BaseRedis::getBlockmatrixRedis();
        $redis->setex($key, self::EXPIRE_SECONDS, $phrase);

        return [
            'image' => $image,
            'expires_in' => self::EXPIRE_SECONDS,
        ];
    }

    private static function supportsFreeType(): bool
    {
        return function_exists('imagettfbbox') && function_exists('imagettftext');
    }

    private static function buildTtfCaptcha(string $code, PhraseBuilder $phraseBuilder): string
    {
        $builder = new CaptchaBuilder($code, $phraseBuilder);
        $builder->setBackgroundColor(220, 210, 230);
        $builder->build(self::WIDTH, self::HEIGHT);

        return $builder->inline();
    }

    private static function buildGdCaptcha(string $code): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        $background = imagecolorallocate($image, 220, 210, 230);
        $lineColor = imagecolorallocate($image, 160, 150, 190);
        $textColor = imagecolorallocate($image, 60, 50, 90);
        $dotColor = imagecolorallocate($image, 120, 110, 150);

        imagefill($image, 0, 0, $background);

        for ($i = 0; $i < 5; $i++) {
            imageline(
                $image,
                random_int(0, self::WIDTH),
                random_int(0, self::HEIGHT),
                random_int(0, self::WIDTH),
                random_int(0, self::HEIGHT),
                $lineColor
            );
        }

        for ($i = 0; $i < 80; $i++) {
            imagesetpixel(
                $image,
                random_int(0, self::WIDTH - 1),
                random_int(0, self::HEIGHT - 1),
                $dotColor
            );
        }

        $font = 5;
        $charWidth = imagefontwidth($font);
        $charHeight = imagefontheight($font);
        $spacing = 6;
        $textWidth = strlen($code) * $charWidth + (strlen($code) - 1) * $spacing;
        $x = (int) ((self::WIDTH - $textWidth) / 2);
        $baseY = (int) ((self::HEIGHT - $charHeight) / 2);

        foreach (str_split($code) as $char) {
            $offsetY = random_int(-3, 3);
            imagestring($image, $font, $x, $baseY + $offsetY, $char, $textColor);
            $x += $charWidth + $spacing;
        }

        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($contents);
    }
}
