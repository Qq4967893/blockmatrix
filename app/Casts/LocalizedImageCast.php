<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Scaffold\Framework\Utils\S3;

class LocalizedImageCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_array($value)) {
            return $this->mapLocalizedImageArray($value);
        }

        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return $this->toFileUrl($value);
        }

        $decorated = $this->mapLocalizedImageArray($decoded);

        return json_encode($decorated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return $value;
    }

    private function mapLocalizedImageArray(array $images): array
    {
        foreach ($images as $lang => $path) {
            if (is_array($path)) {
                $images[$lang] = $this->mapLocalizedImageArray($path);
                continue;
            }

            $images[$lang] = $this->toFileUrl(is_scalar($path) ? (string) $path : '');
        }

        return $images;
    }

    private function toFileUrl(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) app(S3::class)->fileUrl($value);
    }
}
