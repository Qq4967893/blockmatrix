<?php

namespace App\Http\Requests\AdminUser;

use App\Utils\Email;
use App\Utils\Phone;
use App\Http\Requests\ApiRequest;
use App\Models\Blockmatrix\AdminUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ModifyEmailPhoneRequest extends ApiRequest
{
    protected $stopOnFirstFailure = true;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'email' => function ($attribute, $value, $fail) {
                if (empty($value)) {
                    return;
                }
                if (! Email::is($value)) {
                    $fail(trans('manager.user.email_error'));
                    return;
                }
                $exists = DB::table(DB_BM.'.admin_user')
                    ->where('status', AdminUser::STATUS_NORMAL)
                    ->where('id', '!=', Auth::user()['id'])
                    ->where('email', $value)
                    ->exists();
                if ($exists) {
                    $fail(trans('manager.user.email_exist'));
                }
            },
            'phone' => function ($attribute, $value, $fail) {
                if (empty($value)) {
                    return;
                }
                if (! Phone::is($value)) {
                    $fail(trans('manager.user.phone_error'));
                    return;
                }
                $exists = DB::table(DB_BM.'.admin_user')
                    ->where('status', AdminUser::STATUS_NORMAL)
                    ->where('id', '!=', Auth::user()['id'])
                    ->where('phone', $value)
                    ->exists();
                if ($exists) {
                    $fail(trans('manager.user.phone_exist'));
                }
            },
        ];
    }

    /**
     * 验证钩子
     *
     * @param \Illuminate\Validation\Validator $validator
     *
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(
            function ($validator) {
                if (empty($this->input('email')) && empty($this->input('phone'))) {
                    return $validator->errors()->add('field', trans('manager.user.least_one'));
                }
            }
        );
    }
}
