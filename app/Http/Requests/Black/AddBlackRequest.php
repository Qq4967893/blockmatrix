<?php

namespace App\Http\Requests\Black;

use App\Http\Requests\ApiRequest;
use App\Models\Api\User;
use App\Models\Api\UserProhibition;
use Illuminate\Validation\Rule;

class AddBlackRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code' => ['required', 'integer', 'min:1', Rule::exists(User::class, 'user_code')],
            'reason' => ['required', 'string'],
            'down_line' => ['required', 'integer', Rule::in([0, 1])],
            'privileges' => ['required', 'array', 'min:1'],
            'privileges.*' => ['required', 'integer', 'distinct', Rule::in(UserProhibition::typeKeys())],
        ];
    }

    public function messages(): array
    {
        return [
            'user_code.required' => trans('user.message.not_empty'),
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.min' => trans('public.message.data_error'),
            'user_code.exists' => trans('user.message.not_exist'),
            'reason.required' => trans('public.message.in_complete'),
            'reason.string' => trans('public.message.data_error'),
            'down_line.required' => trans('public.message.data_error'),
            'down_line.integer' => trans('public.message.data_error'),
            'down_line.in' => trans('public.message.data_error'),
            'privileges.required' => trans('public.message.data_error'),
            'privileges.array' => trans('public.message.data_error'),
            'privileges.min' => trans('public.message.data_error'),
            'privileges.*.required' => trans('public.message.data_error'),
            'privileges.*.integer' => trans('public.message.data_error'),
            'privileges.*.distinct' => trans('public.message.data_error'),
            'privileges.*.in' => trans('public.message.data_error'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $downLine = $this->input('down_line', 1);
        $privileges = $this->input('privileges', UserProhibition::typeKeys());

        if (is_string($privileges)) {
            $normalizedPrivileges = str_replace('，', ',', trim($privileges));
            $normalizedPrivileges = preg_replace('/\s*,\s*/', ',', $normalizedPrivileges) ?? '';
            $privileges = $normalizedPrivileges === ''
                ? []
                : array_map('intval', explode(',', trim($normalizedPrivileges, ',')));
        } elseif (is_array($privileges)) {
            $privileges = array_map('intval', $privileges);
        }

        $this->merge([
            'down_line' => is_numeric($downLine) ? (int) $downLine : $downLine,
            'privileges' => $privileges,
        ]);
    }
}
