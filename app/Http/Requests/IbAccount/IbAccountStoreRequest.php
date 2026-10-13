<?php

namespace App\Http\Requests\IbAccount;

use App\Http\Requests\ApiRequest;

class IbAccountStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_code.required' => trans('public.message.data_error'),
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.min' => trans('public.message.data_error'),
        ];
    }
}
