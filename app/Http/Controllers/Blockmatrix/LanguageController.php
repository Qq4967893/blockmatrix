<?php

namespace App\Http\Controllers\Blockmatrix;

use App\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;

class LanguageController extends BaseController
{
    /**
     * 获取语言选项
     */
    public function getLang(): JsonResponse
    {
        $languages = config('lang.languages', []);

        return $this->succeed([
            'lang_options' => $this->buildLangOptions($languages),
        ]);
    }

    private function buildLangOptions(array $languages): array
    {
        $ordered = [];
        foreach ($languages as $code => $label) {
            if (! is_string($code) || ! is_string($label)) {
                continue;
            }

            $ordered[] = [
                'key'   => $code,
                'value' => $this->resolveLabel($code, $label),
            ];
        }

        return $ordered;
    }

    private function resolveLabel(string $code, string $default): string
    {
        $translated = trans("lang.languages.$code");

        return $translated === "lang.languages.$code" ? $default : (string) $translated;
    }
}
