<?php

namespace App\Http\Requests\AdminUser;

use App\Http\Requests\ApiRequest;
use App\Rules\PasswordFormat;

class ChangePasswordRequest extends ApiRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            /* 旧密码 */
            'old_passwd'  => ['required', 'string', 'current_password'],
            /* 新密码 */
            'new_passwd'  => ['required', 'string', new PasswordFormat()],
            /* 确认密码 */
            'conf_passwd' => ['required', 'same:new_passwd'],
        ];
    }

    public function messages()
    {
        return [
            'old_passwd.required'         => trans('manager.password.not_empty'),
            'old_passwd.current_password' => trans('manager.password.err'),
            'new_passwd.required'         => trans('manager.password.not_empty'),
            'conf_passwd.required'        => trans('manager.password.not_empty'),
            'conf_passwd.same'            => trans('manager.password.consistent'),
        ];
    }
}
