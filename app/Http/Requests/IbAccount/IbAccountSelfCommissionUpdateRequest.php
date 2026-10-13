<?php

namespace App\Http\Requests\IbAccount;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class IbAccountSelfCommissionUpdateRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_self_commission_enabled' => ['required', 'integer', Rule::in([0, 1])],
        ];
    }

    public function messages(): array
    {
        return [
            'is_self_commission_enabled.required' => trans('public.message.data_error'),
            'is_self_commission_enabled.integer' => trans('public.message.data_error'),
            'is_self_commission_enabled.in' => trans('public.message.data_error'),
        ];
    }
}
