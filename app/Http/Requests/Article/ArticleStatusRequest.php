<?php

namespace App\Http\Requests\Article;

use App\Http\Requests\ApiRequest;
use App\Models\Api\Article;
use Illuminate\Validation\Rule;

class ArticleStatusRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'integer',
                Rule::in(Article::STATUS_UPDATE_OPTIONS),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => trans('article.validation.status_required'),
            'status.integer' => trans('article.validation.status_integer'),
            'status.in' => trans('article.validation.status_in'),
        ];
    }
}
