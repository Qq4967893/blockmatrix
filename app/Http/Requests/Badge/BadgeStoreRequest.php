<?php

namespace App\Http\Requests\Badge;

use App\Http\Requests\ApiRequest;
use App\Models\Api\Badge;
use Illuminate\Validation\Rule;

class BadgeStoreRequest extends ApiRequest
{
    private const RELATION_SUPPORTED_EVENT_KEYS = ['close_trade'];

    private const RELATION_OPTIONS = ['or', 'and'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $langKeys = array_keys(config('lang.languages', []));
        $eventKeys = array_column(Badge::eventOptions(), 'key');

        return [
            'icon' => ['required', 'string'],
            'short_name' => ['required', 'string', 'min:1', 'max:10'],
            'event_key' => ['required', 'string', Rule::in($eventKeys)],
            'event_params' => ['nullable', 'array'],
            'relation' => [
                Rule::requiredIf(fn () => in_array(
                    (string) $this->input('event_key'),
                    self::RELATION_SUPPORTED_EVENT_KEYS,
                    true
                )),
                'nullable',
                'string',
                Rule::in(self::RELATION_OPTIONS),
            ],
            'locale_configs' => ['required', 'array', 'min:1'],
            'locale_configs.*.lang' => ['required', 'string', Rule::in($langKeys)],
            'locale_configs.*.name' => ['required', 'string'],
            'locale_configs.*.description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'icon.required' => trans('public.message.Incomplete'),
            'icon.string' => trans('public.message.data_error'),
            'short_name.required' => trans('public.message.Incomplete'),
            'short_name.string' => trans('public.message.data_error'),
            'short_name.min' => trans('public.message.Incomplete'),
            'short_name.max' => trans('public.message.data_error'),
            'event_key.required' => trans('public.message.Incomplete'),
            'event_key.in' => trans('public.message.wrong_options'),
            'event_params.array' => trans('public.message.data_error'),
            'relation.required' => trans('public.message.in_complete'),
            'relation.string' => trans('public.message.type_error'),
            'relation.in' => trans('public.message.wrong_options'),
            'locale_configs.required' => trans('public.message.Incomplete'),
            'locale_configs.array' => trans('public.message.data_error'),
            'locale_configs.min' => trans('public.message.Incomplete'),
            'locale_configs.*.lang.required' => trans('public.message.Incomplete'),
            'locale_configs.*.lang.in' => trans('public.message.lang_err'),
            'locale_configs.*.name.required' => trans('public.message.Incomplete'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $configs = $this->input('locale_configs', []);
            if (is_array($configs)) {
                $defaultLang = 'en_US';
                $langs = [];
                $hasEnglish = false;
                foreach ($configs as $config) {
                    if (! is_array($config)) {
                        continue;
                    }
                    $lang = $config['lang'] ?? null;
                    if ($lang === null || $lang === '') {
                        continue;
                    }
                    if ($lang === $defaultLang) {
                        $hasEnglish = true;
                    }

                    if (in_array($lang, $langs, true)) {
                        $validator->errors()->add('locale_configs', trans('badge.message.duplicate_lang'));
                        break;
                    }

                    $langs[] = $lang;
                }

                if (! empty($langs) && ! $hasEnglish) {
                    $validator->errors()->add('locale_configs', trans('public.message.default_lang'));
                }
            }

            $this->validateEventParams($validator);
        });
    }

    private function validateEventParams($validator): void
    {
        if ($validator->errors()->has('event_key') || $validator->errors()->has('event_params')) {
            return;
        }

        $eventKey = (string) $this->input('event_key', '');
        $eventParams = $this->input('event_params', []);
        if (! is_array($eventParams)) {
            return;
        }

        foreach ($this->eventParamDefinitions($eventKey) as $definition) {
            $paramKey = (string) ($definition['param_key'] ?? '');
            if ($paramKey === '') {
                continue;
            }

            [$found, $value] = $this->findEventParamValue($eventParams, $paramKey);
            if (($definition['is_required'] ?? false) && (! $found || $this->isBlankValue($value))) {
                $validator->errors()->add(
                    "event_params.{$paramKey}",
                    trans('public.message.in_complete')
                );

                continue;
            }

            if (! $found || $this->isBlankValue($value) || ! ($definition['is_select_required'] ?? false)) {
                continue;
            }

            $allowedValues = array_column($definition['options'] ?? [], 'value');
            $isAllowed = array_any(
                $allowedValues,
                static fn (mixed $allowed): bool => (string) $allowed === (string) $value
            );
            if (! $isAllowed) {
                $validator->errors()->add(
                    "event_params.{$paramKey}",
                    trans('public.message.wrong_options')
                );
            }
        }
    }

    private function eventParamDefinitions(string $eventKey): array
    {
        foreach (Badge::eventOptions() as $option) {
            if (($option['key'] ?? '') === $eventKey) {
                return is_array($option['event_params'] ?? null)
                    ? $option['event_params']
                    : [];
            }
        }

        return [];
    }

    /**
     * @return array{bool, mixed}
     */
    private function findEventParamValue(array $eventParams, string $paramKey): array
    {
        if (array_key_exists($paramKey, $eventParams)) {
            return [true, $eventParams[$paramKey]];
        }

        foreach ($eventParams as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['param_key'] ?? null) === $paramKey) {
                return [true, $item['param_value'] ?? null];
            }

            if (($item['variable'] ?? null) === $paramKey) {
                return [true, $item['value'] ?? null];
            }

            if (array_key_exists($paramKey, $item)) {
                return [true, $item[$paramKey]];
            }
        }

        return [false, null];
    }

    private function isBlankValue(mixed $value): bool
    {
        return $value === null
            || (is_string($value) && trim($value) === '')
            || (is_array($value) && $value === []);
    }

    protected function prepareForValidation(): void
    {
        $eventParams = $this->input('event_params');
        if (is_string($eventParams) && $eventParams !== '') {
            $decoded = json_decode($eventParams, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['event_params' => $decoded]);
            }
        }
    }
}
