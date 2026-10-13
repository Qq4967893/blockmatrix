<?php

namespace App\Http\Requests\Entries;

use App\Http\Requests\ApiRequest;
use App\Models\Api\AppRelease;
use App\Models\Api\PositionRecord;
use Illuminate\Validation\Rule;

class PositionEntriesRequest extends ApiRequest
{
    public function rules()
    {
        $langKeys = array_keys(config('lang.languages', []));

        return [
            /* 广告位标识 */
            'identifier' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[a-z][a-z0-9_-]*$/'],
            /* 广告id */
            'record_id' => ['sometimes', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'integer', Rule::in(PositionRecord::STATUSES)],
            'platform' => ['required_without:record_id', 'array', 'min:1'],
            'platform.*' => ['required', 'string', Rule::in(AppRelease::supportedPlatforms())],
            'locale_configs' => ['required', 'array', 'min:1'],
            'locale_configs.*.platform' => ['nullable', 'string', Rule::in(AppRelease::supportedPlatforms())],
            'locale_configs.*.lang' => ['required', 'string', Rule::in($langKeys)],
            'locale_configs.*.img_url' => ['required', 'string'],
            'locale_configs.*.link' => ['nullable'],
            'locale_configs.*.target_url' => ['nullable', 'string'],
            'locale_configs.*.target_type' => ['nullable', 'integer'],
            'locale_configs.*.target_param' => ['nullable', 'string'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after_or_equal:start_time'],
        ];
    }

    public function messages()
    {
        $platforms = implode(', ', AppRelease::supportedPlatforms());

        return [
            'identifier.required' => trans('ad.position.validation.identifier_required'),
            'identifier.string' => trans('ad.position.validation.identifier_string'),
            'identifier.min' => trans('ad.position.validation.identifier_min'),
            'identifier.max' => trans('ad.position.validation.identifier_max'),
            'identifier.regex' => trans('ad.position.validation.identifier_regex'),
            'record_id.integer' => trans('ad.position.validation.record_id_integer'),
            'record_id.min' => trans('ad.position.validation.record_id_min'),
            'name.required' => trans('ad.position.validation.record_name_required'),
            'name.string' => trans('ad.position.validation.record_name_string'),
            'name.max' => trans('ad.position.validation.record_name_max'),
            'description.string' => trans('ad.position.validation.description_string'),
            'description.max' => trans('ad.position.validation.description_max'),
            'status.integer' => trans('ad.position.validation.record_status_integer'),
            'status.in' => trans('ad.position.validation.record_status_in'),
            'platform.required_without' => trans('ad.position.validation.platform_required'),
            'platform.array' => trans('ad.position.validation.platform_array'),
            'platform.min' => trans('ad.position.validation.platform_required'),
            'platform.*.required' => trans('ad.position.validation.platform_item_required'),
            'platform.*.string' => trans('ad.position.validation.platform_item_string'),
            'platform.*.in' => trans('ad.position.validation.platform_item_in', ['platforms' => $platforms]),
            'locale_configs.required' => trans('ad.position.validation.locale_configs_required'),
            'locale_configs.array' => trans('ad.position.validation.locale_configs_array'),
            'locale_configs.min' => trans('ad.position.validation.locale_configs_required'),
            'locale_configs.*.platform.string' => trans('ad.position.validation.locale_platform_string'),
            'locale_configs.*.platform.in' => trans('ad.position.validation.locale_platform_in', ['platforms' => $platforms]),
            'locale_configs.*.lang.required' => trans('ad.position.validation.locale_lang_required'),
            'locale_configs.*.lang.string' => trans('ad.position.validation.locale_lang_string'),
            'locale_configs.*.lang.in' => trans('ad.position.validation.locale_lang_in'),
            'locale_configs.*.img_url.required' => trans('ad.position.validation.locale_img_required'),
            'locale_configs.*.img_url.string' => trans('ad.position.validation.locale_img_string'),
            'locale_configs.*.link' => trans('ad.position.validation.locale_link_format'),
            'locale_configs.*.target_url.string' => trans('ad.position.validation.locale_target_url_string'),
            'locale_configs.*.target_type.integer' => trans('ad.position.validation.locale_target_type_integer'),
            'locale_configs.*.target_param.string' => trans('ad.position.validation.locale_target_param_string'),
            'start_time.required' => trans('ad.position.validation.start_time_required'),
            'start_time.date' => trans('ad.position.validation.start_time_date'),
            'end_time.required' => trans('ad.position.validation.end_time_required'),
            'end_time.date' => trans('ad.position.validation.end_time_date'),
            'end_time.after_or_equal' => trans('ad.position.add_time_error'),
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $localeConfigs = $this->input('locale_configs', []);
            if (empty($localeConfigs) || ! is_array($localeConfigs)) {
                return;
            }

            $platforms = $this->normalizePlatformInput($this->input('platform', []));
            $resolvedLangKeys = [];
            $hasDefaultByPlatform = [];
            $hasCommonDefault = false;
            $defaultLang = 'en_US';
            foreach ($localeConfigs as $index => $config) {
                if (! is_array($config)) {
                    continue;
                }

                $lang = $config['lang'] ?? null;
                if ($lang !== null && $lang !== '') {
                    $configPlatform = $this->normalizeLocaleConfigPlatform($config['platform'] ?? null);
                    if ($configPlatform !== '' && ! empty($platforms) && ! in_array($configPlatform, $platforms, true)) {
                        $validator->errors()->add(
                            "locale_configs.$index.platform",
                            trans('ad.position.validation.locale_platform_not_selected')
                        );
                    }

                    $resolvedPlatforms = $configPlatform === '' ? $platforms : [$configPlatform];
                    if (empty($resolvedPlatforms)) {
                        $resolvedPlatforms = ['*'];
                    }
                    foreach ($resolvedPlatforms as $resolvedPlatform) {
                        $langKey = $resolvedPlatform . ':' . $lang;
                        if (isset($resolvedLangKeys[$langKey])) {
                            $validator->errors()->add('locale_configs', trans('public.message.repeat_lang'));
                        }
                        $resolvedLangKeys[$langKey] = true;
                    }

                    if ($lang === $defaultLang) {
                        if ($configPlatform === '') {
                            $hasCommonDefault = true;
                        } else {
                            $hasDefaultByPlatform[$configPlatform] = true;
                        }
                    }
                }

                $link = $config['link'] ?? ($config['target_url'] ?? null);
                if ($link !== null && $link !== '' && ! $this->isValidLink($link)) {
                    $validator->errors()->add(
                        "locale_configs.$index.link",
                        trans('ad.position.validation.locale_link_format')
                    );
                }
            }

            if (! empty($resolvedLangKeys) && ! $hasCommonDefault && ! $this->hasDefaultForEveryPlatform($platforms, $hasDefaultByPlatform)) {
                $validator->errors()->add('locale_configs', trans('public.message.default_lang'));
            }

            if (! empty($platforms) && count($platforms) !== count(array_unique($platforms))) {
                $validator->errors()->add('platform', trans('ad.position.validation.platform_duplicate'));
            }
        });
    }

    protected function prepareForValidation()
    {
        $routeRecordId = $this->route('record');
        if ($routeRecordId !== null && ! $this->has('record_id')) {
            $this->merge([
                'record_id' => (int) $routeRecordId,
            ]);
        }

        $identifierInput = $this->input('identifier', null);
        if ($identifierInput !== null) {
            $this->merge([
                'identifier' => $this->normalizeIdentifierValue($identifierInput),
            ]);
        }

        $platformInput = $this->input('platform', null);
        if ($platformInput !== null) {
            $this->merge([
                'platform' => $this->normalizePlatformInput($platformInput),
            ]);
        }

        $localeConfigs = $this->input('locale_configs');
        if (is_array($localeConfigs)) {
            foreach ($localeConfigs as $index => $config) {
                if (! is_array($config)) {
                    continue;
                }

                if (array_key_exists('target_param', $config) && $config['target_param'] !== null) {
                    $localeConfigs[$index]['target_param'] = $this->normalizeTargetParamValue($config['target_param']);
                }

                if (! array_key_exists('link', $config) && array_key_exists('target_url', $config)) {
                    $localeConfigs[$index]['link'] = $config['target_url'];
                }
            }

            $this->merge([
                'locale_configs' => $localeConfigs,
            ]);
        }
    }

    private function normalizeIdentifierValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function normalizePlatformInput(mixed $platform): array
    {
        if (is_array($platform)) {
            return array_values(array_filter(array_map(function ($item) {
                return is_scalar($item) ? trim((string) $item) : '';
            }, $platform), function ($item) {
                return $item !== '';
            }));
        }

        if (is_object($platform)) {
            return $this->normalizePlatformInput((array) $platform);
        }

        if (is_string($platform) && $platform !== '') {
            $trimmed = trim($platform);
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->normalizePlatformInput($decoded);
            }

            $trimmed = trim($trimmed, "[] \t\n\r\0\x0B");

            return array_values(array_filter(array_map('trim', explode(',', $trimmed)), function ($item) {
                return $item !== '';
            }));
        }

        if (is_scalar($platform) && $platform !== '') {
            return [trim((string) $platform)];
        }

        return [];
    }

    private function normalizeLocaleConfigPlatform(mixed $platform): string
    {
        $platforms = $this->normalizePlatformInput($platform);

        return $platforms[0] ?? '';
    }

    /**
     * @param array<int, string> $platforms
     * @param array<string, bool> $hasDefaultByPlatform
     */
    private function hasDefaultForEveryPlatform(array $platforms, array $hasDefaultByPlatform): bool
    {
        if (empty($platforms)) {
            return ! empty($hasDefaultByPlatform);
        }

        foreach ($platforms as $platform) {
            if (empty($hasDefaultByPlatform[$platform])) {
                return false;
            }
        }

        return true;
    }

    private function normalizeTargetParamValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? '' : $encoded;
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function isValidLink(mixed $value): bool
    {
        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            return true;
        }

        if (! is_string($value)) {
            return false;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if (ctype_digit($trimmed)) {
            return true;
        }

        return (bool) filter_var($trimmed, FILTER_VALIDATE_URL);
    }
}
