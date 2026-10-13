<?php

namespace App\Http\Requests\User;

use App\Http\Requests\ApiRequest;

class UserProfileUpdateRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code'  => ['required', 'integer', 'min:1'],
            'first_name' => ['nullable', 'string', 'max:255', "regex:/^[A-Za-z][A-Za-z\\s\\-']*$/"],
            'last_name'  => ['nullable', 'string', 'max:255', "regex:/^[A-Za-z][A-Za-z\\s\\-']*$/"],
            'country'    => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_code.required'  => trans('public.message.data_error'),
            'user_code.integer'   => trans('public.message.data_error'),
            'user_code.min'       => trans('public.message.data_error'),
            'first_name.string'   => trans('public.message.data_error'),
            'first_name.max'      => trans('public.message.data_error'),
            'first_name.regex'    => trans('public.message.data_error'),
            'last_name.string'    => trans('public.message.data_error'),
            'last_name.max'       => trans('public.message.data_error'),
            'last_name.regex'     => trans('public.message.data_error'),
            'country.string'      => trans('public.message.data_error'),
            'country.size'        => trans('public.message.data_error'),
            'country.regex'       => trans('public.message.data_error'),
        ];
    }
}
