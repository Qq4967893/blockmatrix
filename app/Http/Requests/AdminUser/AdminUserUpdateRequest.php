<?php

namespace App\Http\Requests\AdminUser;

use App\Utils\Email;
use App\Utils\Phone;
use App\Http\Requests\ApiRequest;
use App\Models\Blockmatrix\AdminUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use function trans;

class AdminUserUpdateRequest extends ApiRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            /* 姓名 */
            'login_name' => ['required', Rule::unique(DB_BM.'.admin_user', 'login_name')->ignore($this->input('manager_id'))],
            /* 角色id */
            'role_id'  => ['required'],
            /* 层级id */
            'level_id' => ['nullable', 'integer', 'min:0'],
            /* 邮箱 */
            'email'    => function ($attribute, $value, $fail) {
                if (! empty($value)) {
                    if (! Email::is($value)) {
                        $fail(trans('manager.user.email_error'));
                        return;
                    }
                    $exists = DB::table(DB_BM.'.admin_user')
                        ->where('status', AdminUser::STATUS_NORMAL)
                        ->where('id', '!=', $this->input('manager_id'))
                        ->where('email', $value)
                        ->exists();
                    if ($exists) {
                        $fail(trans('manager.user.email_exist'));
                    }
                }
            },
            /* 手机号 */
            'phone'    => function ($attribute, $value, $fail) {
                if (! empty($value)) {
                    if (! Phone::is($value)) {
                        $fail(trans('manager.user.phone_error'));
                        return;
                    }
                    $exists = DB::table(DB_BM.'.admin_user')
                        ->where('status', AdminUser::STATUS_NORMAL)
                        ->where('id', '!=', $this->input('manager_id'))
                        ->where('phone', $value)
                        ->exists();
                    if ($exists) {
                        $fail(trans('manager.user.phone_exist'));
                    }
                }
            },
             /* 直属领导id */
            'leader_id'   => ['nullable', 'integer'],
            /*用户id */
            'manager_id'   => ['integer'],
        ];
    }

    public function messages()
    {
        return [
            'login_name.required' => trans('manager.user.multi_field_not_empty'),
            'login_name.unique'   => trans('manager.user.name_exists'),
            'password.required' => trans('manager.user.multi_field_not_empty'),
            'role_id.required'  => trans('manager.user.multi_field_not_empty'),
            'level_id.integer'  => trans('public.message.data_error'),
            'level_id.min'      => trans('public.message.data_error'),
            'email_error'       => trans('manager.user.email_error'),
        ];
    }
}
