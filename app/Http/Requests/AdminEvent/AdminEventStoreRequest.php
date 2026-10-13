<?php

namespace App\Http\Requests\AdminEvent;

use App\Http\Requests\ApiRequest;

class AdminEventStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code' => ['required', 'integer', 'gt:0'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_code.required' => trans('public.message.data_error'),
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.gt' => trans('public.message.data_error'),
            'amount.required' => trans('public.message.data_error'),
            'amount.numeric' => trans('public.message.data_error'),
            'amount.min' => trans('public.message.data_error'),
        ];
    }
}
