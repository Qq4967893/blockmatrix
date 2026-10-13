<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class EmailCaptchaLookupRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => trans('public.message.in_complete'),
            'email.string' => trans('public.message.type_error'),
            'email.email' => trans('public.message.data_error'),
            'reason.required' => trans('public.message.in_complete'),
            'reason.string' => trans('public.message.type_error'),
            'reason.max' => trans('public.message.data_error'),
        ];
    }
}
