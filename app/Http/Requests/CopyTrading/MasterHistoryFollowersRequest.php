<?php

namespace App\Http\Requests\CopyTrading;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Enums\TypeTradeAccount;

class MasterHistoryFollowersRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'master_type' => ['nullable', 'integer', Rule::in([TypeTradeAccount::UTA->value, TypeTradeAccount::BonusFund->value])],
        ];
    }

    public function messages(): array
    {
        return [
            'master_type.integer' => trans('public.message.data_error'),
            'master_type.in' => trans('public.message.data_error'),
        ];
    }
}
