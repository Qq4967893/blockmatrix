<?php

namespace App\Http\Requests\AppRelease;

use App\Http\Requests\ApiRequest;
use App\Models\Api\AppRelease;
use Illuminate\Validation\Rule;

class AppReleaseStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 新增客户端版本参数
     *
     * body:
     * - platform:string 平台标识(miniapp/testflight/ios/android/webtrade)
     * - version:string 版本号
     * - build_number:int 构建号(testflight 必填且 >0，其它平台可传 0)
     * - locale_configs:array 多语言配置[{lang,title,description}]
     * - is_forced_update:int 是否强更(0/1)
     * - download_url:string|null 下载地址
     *
     * 前端注意：
     * - locale_configs 至少 1 条，且必须包含 en_US
     * - locale_configs 里 lang 不能重复
     * - 非 testflight 平台 build_number 必须传 0
     */
    public function rules(): array
    {
        $langKeys = array_keys(config('lang.languages', []));

        return [
            'platform' => ['required', 'string', Rule::in(AppRelease::supportedPlatforms())],
            'version' => ['required', 'string', 'max:50'],
            'build_number' => ['required', 'integer', 'min:0'],
            'locale_configs' => ['required', 'array', 'min:1'],
            'locale_configs.*.lang' => ['required', 'string', Rule::in($langKeys)],
            'locale_configs.*.title' => ['required', 'string', 'max:200'],
            'locale_configs.*.description' => ['required', 'string', 'max:2000'],
            'is_forced_update' => ['required', 'integer', Rule::in([0, 1])],
            'download_url' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'platform.required' => trans('public.message.in_complete'),
            'platform.in' => trans('public.message.wrong_options'),
            'version.required' => trans('public.message.in_complete'),
            'version.max' => trans('public.message.wrong_threshold'),
            'build_number.required' => trans('public.message.in_complete'),
            'build_number.integer' => trans('public.message.type_error'),
            'build_number.min' => trans('public.message.wrong_threshold'),
            'locale_configs.required' => trans('public.message.in_complete'),
            'locale_configs.array' => trans('public.message.type_error'),
            'locale_configs.min' => trans('public.message.in_complete'),
            'locale_configs.*.lang.required' => trans('public.message.in_complete'),
            'locale_configs.*.lang.in' => trans('public.message.lang_err'),
            'locale_configs.*.title.required' => trans('public.message.in_complete'),
            'locale_configs.*.description.required' => trans('public.message.in_complete'),
            'is_forced_update.required' => trans('public.message.in_complete'),
            'is_forced_update.integer' => trans('public.message.type_error'),
            'is_forced_update.in' => trans('public.message.wrong_options'),
            'download_url.max' => trans('public.message.wrong_threshold'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateLocaleConfigs($validator, 'locale_configs');

            $platform = (string) $this->input('platform', '');
            $buildNumber = (int) $this->input('build_number', 0);
            if ($platform === 'testflight' && $buildNumber <= 0) {
                $validator->errors()->add('build_number', trans('public.message.wrong_threshold'));
            }
            if ($platform !== 'testflight' && $buildNumber !== 0) {
                $validator->errors()->add('build_number', trans('public.message.wrong_options'));
            }

            // 代码层提前拦截唯一键冲突：platform + version + build_number
            if ($validator->errors()->isEmpty()) {
                $version = trim((string) $this->input('version', ''));
                if ($platform !== '' && $version !== '') {
                    $exists = AppRelease::query()
                        ->where('platform', $platform)
                        ->where('version', $version)
                        ->where('build_number', $buildNumber)
                        ->exists();
                    if ($exists) {
                        $validator->errors()->add('version', trans('public.message.already_exist'));
                    }
                }
            }
        });
    }

    private function validateLocaleConfigs($validator, string $field): void
    {
        $localeConfigs = $this->input($field, []);
        if (! is_array($localeConfigs)) {
            return;
        }

        $langs = [];
        $hasDefault = false;
        $defaultLang = 'en_US';

        foreach ($localeConfigs as $config) {
            if (! is_array($config)) {
                continue;
            }

            $lang = trim((string) ($config['lang'] ?? ''));
            if ($lang === '') {
                continue;
            }

            if ($lang === $defaultLang) {
                $hasDefault = true;
            }
            $langs[] = $lang;
        }

        if (count($langs) !== count(array_unique($langs))) {
            $validator->errors()->add($field, trans('public.message.repeat_lang'));
        }

        if (! empty($langs) && ! $hasDefault) {
            $validator->errors()->add($field, trans('public.message.default_lang'));
        }
    }
}
