<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class CaptchaRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'key' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.required' => trans('login.key_empty')
        ];
    }
}
