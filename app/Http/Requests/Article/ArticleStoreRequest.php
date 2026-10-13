<?php

namespace App\Http\Requests\Article;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Enums\TypeArticleCategory;

class ArticleStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $langKeys = array_keys(config('lang.languages', []));
        $categories = array_map(
            static fn (TypeArticleCategory $category): int => $category->value,
            TypeArticleCategory::cases(),
        );

        return [
            'category' => ['required', 'array', 'min:1'],
            'category.*' => ['required', 'integer', Rule::in($categories)],
            'is_top' => ['required', 'integer', Rule::in([0, 1])],
            'thumbnail' => ['nullable', 'string', 'max:500', 'not_regex:/^(?:[a-z][a-z0-9+.-]*:)?\/\//i'],
            'locale_configs' => ['required_without_all:title,content', 'array', 'min:1'],
            'locale_configs.*.lang' => ['required', 'string', Rule::in($langKeys)],
            'locale_configs.*.title' => ['required', 'string', 'max:255'],
            'locale_configs.*.content' => ['required', 'string'],
            'title' => ['required_without:locale_configs', 'array', 'min:1'],
            'title.*' => ['required', 'string', 'max:255'],
            'content' => ['required_without:locale_configs', 'array', 'min:1'],
            'content.*' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => trans('article.validation.category_required'),
            'category.array' => trans('article.validation.category_array'),
            'category.min' => trans('article.validation.category_required'),
            'category.*.required' => trans('article.validation.category_required'),
            'category.*.integer' => trans('article.validation.category_integer'),
            'category.*.in' => trans('article.validation.category_in'),
            'is_top.required' => trans('article.validation.is_top_required'),
            'is_top.integer' => trans('article.validation.is_top_integer'),
            'is_top.in' => trans('article.validation.is_top_in'),
            'thumbnail.string' => trans('article.validation.thumbnail_string'),
            'thumbnail.max' => trans('article.validation.thumbnail_max'),
            'thumbnail.not_regex' => trans('article.validation.thumbnail_not_url'),
            'locale_configs.required_without_all' => trans('article.validation.locale_configs_required'),
            'locale_configs.array' => trans('article.validation.locale_configs_array'),
            'locale_configs.min' => trans('article.validation.locale_configs_required'),
            'locale_configs.*.lang.required' => trans('article.validation.locale_lang_required'),
            'locale_configs.*.lang.string' => trans('article.validation.locale_lang_string'),
            'locale_configs.*.lang.in' => trans('public.message.lang_err'),
            'locale_configs.*.title.required' => trans('article.validation.title_required'),
            'locale_configs.*.title.string' => trans('article.validation.title_string'),
            'locale_configs.*.title.max' => trans('article.validation.title_max'),
            'locale_configs.*.content.required' => trans('article.validation.content_required'),
            'locale_configs.*.content.string' => trans('article.validation.content_string'),
            'title.required_without' => trans('article.validation.title_required'),
            'title.array' => trans('article.validation.title_json'),
            'title.min' => trans('article.validation.title_required'),
            'title.*.required' => trans('article.validation.title_required'),
            'title.*.string' => trans('article.validation.title_string'),
            'title.*.max' => trans('article.validation.title_max'),
            'content.required_without' => trans('article.validation.content_required'),
            'content.array' => trans('article.validation.content_json'),
            'content.min' => trans('article.validation.content_required'),
            'content.*.required' => trans('article.validation.content_required'),
            'content.*.string' => trans('article.validation.content_string'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->has('locale_configs')) {
                $this->validateLocaleConfigs($validator);

                return;
            }

            $this->validateLocaleMapLang($validator, 'title');
            $this->validateLocaleMapLang($validator, 'content');
        });
    }

    protected function prepareForValidation(): void
    {
        foreach (['category', 'title', 'content', 'locale_configs'] as $field) {
            $value = $this->input($field);
            if (is_string($value) && trim($value) !== '') {
                $decoded = json_decode(trim($value), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$field => $decoded]);
                }
            }
        }
    }

    private function validateLocaleConfigs($validator): void
    {
        $configs = $this->input('locale_configs', []);
        if (! is_array($configs)) {
            return;
        }

        $langs = [];
        $hasDefault = false;
        foreach ($configs as $config) {
            if (! is_array($config)) {
                continue;
            }

            $lang = trim((string) ($config['lang'] ?? ''));
            if ($lang === '') {
                continue;
            }

            if (in_array($lang, $langs, true)) {
                $validator->errors()->add('locale_configs', trans('public.message.repeat_lang'));
            }

            $langs[] = $lang;
            if ($lang === 'en_US') {
                $hasDefault = true;
            }
        }

        if ($langs !== [] && ! $hasDefault) {
            $validator->errors()->add('locale_configs', trans('public.message.default_lang'));
        }
    }

    private function validateLocaleMapLang($validator, string $field): void
    {
        $values = $this->input($field, []);
        if (! is_array($values)) {
            return;
        }

        foreach (array_keys($values) as $lang) {
            if (! in_array((string) $lang, array_keys(config('lang.languages', [])), true)) {
                $validator->errors()->add($field, trans('public.message.lang_err'));

                return;
            }
        }

        if (! array_key_exists('en_US', $values) || trim((string) $values['en_US']) === '') {
            $validator->errors()->add($field, trans('public.message.default_lang'));
        }
    }
}
