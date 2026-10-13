<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class LoginRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            /** 用户名 */
            'login_name' => 'required|string',
            /** 密码 */
            'password' => 'required|string',
        ];

        if (empty(config('blockmatrix.captcha.none'))) {
            $rules['key'] = 'required|string';
            $rules['captcha'] = 'required|string';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'login_name.required' => trans('public.message.in_complete'),
            'login_name.string'   => trans('public.message.type_error'),
            'password.required'   => trans('public.message.in_complete'),
            'password.string'     => trans('public.message.type_error'),
            'key.required'        => trans('login.key_empty'),
            'key.string'          => trans('public.message.type_error'),
            'captcha.required'       => trans('login.captcha_not_empty'),
            'captcha.string'         => trans('public.message.type_error'),
        ];
    }

    public function expectsJson(): bool
    {
        return true;
    }
}
