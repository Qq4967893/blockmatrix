<?php

namespace App\Http\Requests\CommunityActivity;

use App\Http\Requests\ApiRequest;
use App\Utils\S3FileKey;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Enums\TypeCommunityActivity;
use Scaffold\Framework\Enums\TypeCommunityActivityAudience;
use Scaffold\Framework\Enums\TypeCommunityActivityCity;

class CommunityActivityStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $typeValues = array_map(static fn (TypeCommunityActivity $type): int => $type->value, TypeCommunityActivity::cases());
        $cityValues = array_map(static fn (TypeCommunityActivityCity $city): string => $city->value, TypeCommunityActivityCity::cases());
        $audienceValues = array_map(static fn (TypeCommunityActivityAudience $audience): string => $audience->value, TypeCommunityActivityAudience::cases());
        $langKeys = array_keys(config('lang.languages', []));

        return [
            'type' => ['required', 'integer', Rule::in($typeValues)],
            'city' => ['required', 'string', Rule::in($cityValues)],
            'start_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'end_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'apply_start_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'apply_end_at' => ['required', 'date_format:Y-m-d H:i:s'],
            'audience' => ['required', 'array', 'min:1'],
            'audience.*' => ['required', 'string', Rule::in($audienceValues)],
            'locale_configs' => ['required', 'array', 'min:1'],
            'locale_configs.*.lang' => ['required', 'string', Rule::in($langKeys)],
            'locale_configs.*.name' => ['required', 'string'],
            'locale_configs.*.summary' => ['required', 'string'],
            'locale_configs.*.cover_image' => ['required', 'string', 'regex:'.FILE_PATTERN],
            'locale_configs.*.bg_image' => ['required', 'string', 'regex:'.FILE_PATTERN],
            'locale_configs.*.introduction' => ['required', 'string'],
            'locale_configs.*.content' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => trans('public.message.in_complete'),
            'type.integer' => trans('public.message.type_error'),
            'type.in' => trans('public.message.wrong_options'),
            'city.required' => trans('public.message.in_complete'),
            'city.string' => trans('public.message.type_error'),
            'city.in' => trans('public.message.wrong_options'),
            'start_at.required' => trans('public.message.in_complete'),
            'start_at.date_format' => trans('public.message.type_error'),
            'end_at.required' => trans('public.message.in_complete'),
            'end_at.date_format' => trans('public.message.type_error'),
            'apply_start_at.required' => trans('public.message.in_complete'),
            'apply_start_at.date_format' => trans('public.message.type_error'),
            'apply_end_at.required' => trans('public.message.in_complete'),
            'apply_end_at.date_format' => trans('public.message.type_error'),
            'audience.required' => trans('public.message.in_complete'),
            'audience.array' => trans('public.message.type_error'),
            'audience.min' => trans('public.message.in_complete'),
            'audience.*.required' => trans('public.message.in_complete'),
            'audience.*.string' => trans('public.message.type_error'),
            'audience.*.in' => trans('public.message.wrong_options'),
            'locale_configs.required' => trans('public.message.in_complete'),
            'locale_configs.array' => trans('public.message.type_error'),
            'locale_configs.min' => trans('public.message.in_complete'),
            'locale_configs.*.lang.required' => trans('public.message.in_complete'),
            'locale_configs.*.lang.string' => trans('public.message.type_error'),
            'locale_configs.*.lang.in' => trans('public.message.lang_err'),
            'locale_configs.*.name.required' => trans('public.message.in_complete'),
            'locale_configs.*.name.string' => trans('public.message.type_error'),
            'locale_configs.*.summary.required' => trans('public.message.in_complete'),
            'locale_configs.*.summary.string' => trans('public.message.type_error'),
            'locale_configs.*.cover_image.required' => trans('public.message.in_complete'),
            'locale_configs.*.cover_image.string' => trans('public.message.type_error'),
            'locale_configs.*.cover_image.regex' => trans('public.message.type_error'),
            'locale_configs.*.bg_image.required' => trans('public.message.in_complete'),
            'locale_configs.*.bg_image.string' => trans('public.message.type_error'),
            'locale_configs.*.bg_image.regex' => trans('public.message.type_error'),
            'locale_configs.*.introduction.required' => trans('public.message.in_complete'),
            'locale_configs.*.introduction.string' => trans('public.message.type_error'),
            'locale_configs.*.content.required' => trans('public.message.in_complete'),
            'locale_configs.*.content.string' => trans('public.message.type_error'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->validateLocaleConfigs($validator);
            $this->validateActivityTime($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        foreach (['locale_configs', 'audience'] as $field) {
            $value = $this->input($field);
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge([$field => $decoded]);
            }
        }

        $this->normalizeImageKeys();
    }

    private function normalizeImageKeys(): void
    {
        $configs = $this->input('locale_configs');
        if (! is_array($configs)) {
            return;
        }

        foreach ($configs as &$config) {
            if (! is_array($config)) {
                continue;
            }

            foreach (['cover_image', 'bg_image'] as $field) {
                if (is_string($config[$field] ?? null)) {
                    $config[$field] = S3FileKey::normalize($config[$field]);
                }
            }
        }
        unset($config);

        $this->merge(['locale_configs' => $configs]);
    }

    private function validateLocaleConfigs($validator): void
    {
        $configs = $this->input('locale_configs', []);
        if (! is_array($configs)) {
            return;
        }

        $langs = [];
        foreach ($configs as $config) {
            if (! is_array($config) || ! is_string($config['lang'] ?? null) || $config['lang'] === '') {
                continue;
            }

            $lang = $config['lang'];
            if (in_array($lang, $langs, true)) {
                $validator->errors()->add('locale_configs', trans('public.message.repeat_lang'));

                return;
            }

            $langs[] = $lang;
        }

        if ($langs !== [] && ! in_array('en_US', $langs, true)) {
            $validator->errors()->add('locale_configs', trans('public.message.default_lang'));
        }
    }

    private function validateActivityTime($validator): void
    {
        $startAt = $this->input('start_at');
        $endAt = $this->input('end_at');
        $applyStartAt = $this->input('apply_start_at');
        $applyEndAt = $this->input('apply_end_at');
        if (! is_string($startAt) || ! is_string($endAt) || ! is_string($applyStartAt) || ! is_string($applyEndAt)) {
            return;
        }

        $startTimestamp = strtotime($startAt);
        $endTimestamp = strtotime($endAt);
        $applyStartTimestamp = strtotime($applyStartAt);
        $applyEndTimestamp = strtotime($applyEndAt);
        if ($startTimestamp === false || $endTimestamp === false || $applyStartTimestamp === false || $applyEndTimestamp === false) {
            return;
        }

        if ($endTimestamp < $startTimestamp) {
            $validator->errors()->add('end_at', trans('public.message.wrong_threshold'));
        }

        if ($applyEndTimestamp < $applyStartTimestamp) {
            $validator->errors()->add('apply_end_at', trans('public.message.wrong_threshold'));
        }

        // 报名时间段与活动时间段不允许交叉；首尾无缝衔接（如 apply_end_at == start_at）不算交叉，中间留空档也允许
        if ($applyStartTimestamp < $endTimestamp && $startTimestamp < $applyEndTimestamp) {
            $validator->errors()->add('apply_end_at', trans('public.message.wrong_time_select'));
        }
    }
}
