<?php

namespace App\Http\Requests\White;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use App\Models\Api\User;

class AddWhiteRequest extends ApiRequest
{
    public function rules()
    {
        return [
            'user_code' => ['required', 'integer', Rule::exists(User::class)->whereNot('type', User::TYPE_TEST)],
        ];
    }

    public function withValidator($validator)
    {

    }

    public function messages()
    {
        return [
            'user_code.required' => trans('user.message.not_empty'),
            'user_code.exists'   => trans('user.white.already'),
        ];
    }
}
