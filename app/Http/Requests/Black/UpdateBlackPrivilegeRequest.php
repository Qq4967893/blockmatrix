<?php

namespace App\Http\Requests\Black;

use App\Http\Requests\ApiRequest;
use App\Models\Api\User;
use App\Models\Api\UserProhibition;
use Illuminate\Validation\Rule;

class UpdateBlackPrivilegeRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code' => ['required', 'integer', 'min:1', Rule::exists(User::class, 'user_code')],
            'privilege_type' => ['required', 'array', 'min:1'],
            'privilege_type.*' => ['required', 'integer', 'distinct', Rule::in(UserProhibition::typeKeys())],
        ];
    }

    public function messages(): array
    {
        return [
            'user_code.required' => trans('user.message.not_empty'),
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.min' => trans('public.message.data_error'),
            'user_code.exists' => trans('user.message.not_exist'),
            'privilege_type.required' => trans('public.message.data_error'),
            'privilege_type.array' => trans('public.message.data_error'),
            'privilege_type.min' => trans('public.message.data_error'),
            'privilege_type.*.required' => trans('public.message.data_error'),
            'privilege_type.*.integer' => trans('public.message.data_error'),
            'privilege_type.*.distinct' => trans('public.message.data_error'),
            'privilege_type.*.in' => trans('public.message.data_error'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $privilegeTypes = $this->input('privilege_type', []);

        if (is_string($privilegeTypes)) {
            $normalizedPrivilegeTypes = str_replace('，', ',', trim($privilegeTypes));
            $normalizedPrivilegeTypes = preg_replace('/\s*,\s*/', ',', $normalizedPrivilegeTypes) ?? '';
            $privilegeTypes = $normalizedPrivilegeTypes === ''
                ? []
                : array_map('intval', explode(',', trim($normalizedPrivilegeTypes, ',')));
        } elseif (is_array($privilegeTypes)) {
            $privilegeTypes = array_map('intval', $privilegeTypes);
        }

        $this->merge([
            'privilege_type' => $privilegeTypes,
        ]);
    }
}
