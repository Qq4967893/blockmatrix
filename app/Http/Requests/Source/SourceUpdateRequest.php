<?php

namespace App\Http\Requests\Source;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class SourceUpdateRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $langKeys = array_keys(config('lang.languages', []));

        return [
            'id'                       => ['required', Rule::exists('api.ts_source', 'id')],
            'source_type'              => ['required', 'string', 'max:10'],
            /* 品种图标命名：source/......jpg*/
            'icon'                     => ['required', 'string'],
            /* 多语言配置 */
            'locale_configs'           => ['required', 'array', 'min:1'],
            'locale_configs.*.lang'    => ['required', 'string', Rule::in($langKeys)],
            'locale_configs.*.name'    => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required'                    => trans('public.message.id_empty'),
            'id.exists'                      => trans('public.message.data_error'),
            'source_type.required'           => trans('public.message.empty_content'),
            'source_type.max'                => trans('public.message.format_err'),
            'icon.required'                  => trans('public.message.empty_content'),
            'locale_configs.required'        => trans('public.message.Incomplete'),
            'locale_configs.array'           => trans('public.message.data_error'),
            'locale_configs.min'             => trans('public.message.Incomplete'),
            'locale_configs.*.lang.required' => trans('public.message.Incomplete'),
            'locale_configs.*.lang.in'       => trans('public.message.lang_err'),
            'locale_configs.*.name.required' => trans('public.message.empty_content'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $configs = $this->input('locale_configs', []);
            $langs   = [];
            foreach ($configs as $config) {
                if (! is_array($config)) {
                    continue;
                }
                $lang = $config['lang'] ?? null;
                if ($lang === null || $lang === '') {
                    continue;
                }
                if (isset($langs[$lang])) {
                    $validator->errors()->add('locale_configs', trans('public.message.repeat_lang'));
                    return;
                }
                $name = $config['name'] ?? '';
                if ($name === '') {
                    $validator->errors()->add('locale_configs', trans('public.message.empty_content'));
                    return;
                }
                $langs[$lang] = true;
            }

            if (empty($langs)) {
                $validator->errors()->add('locale_configs', trans('public.message.default_lang'));
                return;
            }

            if (! isset($langs[config('app.fallback_locale')])) {
                $validator->errors()->add('locale_configs', trans('public.message.default_lang'));
            }
        });
    }
}
