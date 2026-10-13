<?php

namespace App\Http\Requests\IbCommissionRule;

use App\Http\Requests\ApiRequest;

class IbCommissionRuleListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string'],
            'level_name' => ['nullable', 'string'],
            'is_effective' => ['nullable', 'integer', 'in:0,1'],
            'review_status' => ['nullable', 'integer', 'in:0,1,2,3,4'],
            'limit' => ['nullable', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'is_effective.integer' => trans('public.message.data_error'),
            'is_effective.in' => trans('public.message.data_error'),
            'review_status.integer' => trans('public.message.data_error'),
            'review_status.in' => trans('public.message.data_error'),
            'limit.integer' => trans('public.message.data_error'),
            'limit.min' => trans('public.message.data_error'),
            'offset.integer' => trans('public.message.data_error'),
            'offset.min' => trans('public.message.data_error'),
        ];
    }
}
