<?php

namespace App\Http\Requests\Currency;

use App\Http\Requests\ApiRequest;

class CurrencyUpdateRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id'               => ['required', 'integer', 'min:1'],
            'currency'         => ['prohibited'],
            'spread_deposit'   => ['required', 'numeric'],
            'spread_withdraw'  => ['required', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required'              => trans('public.message.data_error'),
            'id.integer'               => trans('public.message.data_error'),
            'id.min'                   => trans('public.message.data_error'),
            'currency.prohibited'      => trans('public.message.data_error'),
            'spread_deposit.required'  => trans('public.message.data_error'),
            'spread_deposit.numeric'   => trans('public.message.data_error'),
            'spread_withdraw.required' => trans('public.message.data_error'),
            'spread_withdraw.numeric'  => trans('public.message.data_error'),
        ];
    }
}
