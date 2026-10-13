<?php

namespace App\Http\Requests;

trait ValidateTrait
{
    /**
     * 验证
     *
     * @param string $value 参数内容
     *
     * @return array|\Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Translation\Translator|string|null
     */
    public function validateMultilingual($value)
    {

        $langArr = array_keys(config('lang.languages', []));
        $valueArr = [];

        if (is_string($value)) {
            //验证是否重复添加
            foreach ($langArr as $lang) {
                if (substr_count($value, $lang) > 1) {
                    return trans('public.message.repeat_lang');
                }
            }

            $valueArr = json_decode($value, true);
            if (json_last_error() != JSON_ERROR_NONE) {
                return trans('user.sys_message.format_err');
            }
        } elseif (is_array($value)) {
            $valueArr = $value;
        } else {
            return trans('user.sys_message.format_err');
        }

        $hasValue = false;
        foreach ($valueArr as $lang => $item) {
            if (! in_array($lang, $langArr)) {
                return trans('public.message.lang_err');
            }
            if (empty($item)) {
                return trans('user.sys_message.title_empty');
            }
            $hasValue = true;
        }

        if (! $hasValue) {
            return trans('public.message.default_lang');
        }
        return '';
    }
}
