<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiRequest;

class UserTransferRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code' => ['required', 'integer', 'min:1'],
            'target_user_code' => ['required', 'integer', 'min:1', 'different:user_code'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_code.required' => trans('public.message.data_error'),
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.min' => trans('public.message.data_error'),
            'target_user_code.required' => trans('public.message.data_error'),
            'target_user_code.integer' => trans('public.message.data_error'),
            'target_user_code.min' => trans('public.message.data_error'),
            'target_user_code.different' => trans('public.message.data_error'),
        ];
    }
}
