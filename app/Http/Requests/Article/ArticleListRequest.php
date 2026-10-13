<?php

namespace App\Http\Requests\Article;

use App\Http\Requests\ApiRequest;
use App\Models\Api\Article;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Enums\TypeArticleCategory;

class ArticleListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categories = array_map(
            static fn (TypeArticleCategory $category): int => $category->value,
            TypeArticleCategory::cases(),
        );

        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'array', 'min:1'],
            'category.*' => ['required', 'integer', Rule::in($categories)],
            'status' => ['nullable', 'integer', Rule::in(Article::LIST_STATUSES)],
            'is_top' => ['nullable', 'integer', Rule::in([0, 1])],
            'published_at_from' => ['nullable', 'date_format:Y-m-d H:i:s'],
            'published_at_to' => ['nullable', 'date_format:Y-m-d H:i:s', 'after_or_equal:published_at_from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => trans('article.validation.keyword_string'),
            'keyword.max' => trans('article.validation.keyword_max'),
            'category.array' => trans('article.validation.category_array'),
            'category.min' => trans('article.validation.category_required'),
            'category.*.required' => trans('article.validation.category_required'),
            'category.*.integer' => trans('article.validation.category_integer'),
            'category.*.in' => trans('article.validation.category_in'),
            'status.integer' => trans('article.validation.status_integer'),
            'status.in' => trans('article.validation.list_status_in'),
            'is_top.integer' => trans('article.validation.is_top_integer'),
            'is_top.in' => trans('article.validation.is_top_in'),
            'published_at_from.date_format' => trans('public.message.wrong_format_date'),
            'published_at_to.date_format' => trans('public.message.wrong_format_date'),
            'published_at_to.after_or_equal' => trans('public.message.wrong_time_select'),
            'limit.integer' => trans('public.message.type_error'),
            'limit.min' => trans('public.message.wrong_threshold'),
            'limit.max' => trans('public.message.wrong_threshold'),
            'offset.integer' => trans('public.message.type_error'),
            'offset.min' => trans('public.message.wrong_threshold'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $category = $this->input('category');
        if (is_string($category) && trim($category) !== '') {
            $decoded = json_decode(trim($category), true);
            $category = json_last_error() === JSON_ERROR_NONE && is_array($decoded)
                ? $decoded
                : explode(',', trim($category, "[] \t\n\r\0\x0B"));
        }

        if ($category !== null) {
            $this->merge([
                'category' => array_values(array_filter(array_map(
                    static fn (mixed $item): int => is_numeric($item) ? (int) $item : 0,
                    is_array($category) ? $category : [],
                ))),
            ]);
        }
    }
}
