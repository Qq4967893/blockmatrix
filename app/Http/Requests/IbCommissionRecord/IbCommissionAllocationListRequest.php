<?php

namespace App\Http\Requests\IbCommissionRecord;

use App\Http\Requests\ApiRequest;

class IbCommissionAllocationListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code' => ['nullable', 'integer', 'min:1'],
            'level_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.min' => trans('public.message.data_error'),
            'level_id.integer' => trans('public.message.data_error'),
            'level_id.min' => trans('public.message.data_error'),
            'limit.integer' => trans('public.message.data_error'),
            'limit.min' => trans('public.message.data_error'),
            'offset.integer' => trans('public.message.data_error'),
            'offset.min' => trans('public.message.data_error'),
        ];
    }
}
