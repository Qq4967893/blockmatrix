<?php

namespace App\Services\Api\Traits;

use Scaffold\Framework\Utils\S3;

trait ContentImageNormalizer
{
    protected function normalizeContentImages(string $content, ?S3 $s3 = null): string
    {
        $s3 ??= app(S3::class);

        return preg_replace_callback(
            '/<img[^>]+src="([^":]+)"[^>]*>/i',
            function ($matches) use ($s3) {
                $src = $matches[1] ?? '';
                if ($src === '' || str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
                    return $matches[0];
                }

                $fullUrl = $s3->fileUrl($src);

                return str_replace($src, $fullUrl, $matches[0]);
            },
            $content
        );
    }
}
