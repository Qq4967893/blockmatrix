<?php

namespace App\Http\Requests\Currency;

use App\Http\Requests\ApiRequest;

class CurrencyStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency'         => ['required', 'string', 'max:16'],
            'spread_deposit'   => ['required', 'numeric'],
            'spread_withdraw'  => ['required', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'currency.required'        => trans('public.message.data_error'),
            'currency.string'          => trans('public.message.data_error'),
            'currency.max'             => trans('public.message.data_error'),
            'spread_deposit.required'  => trans('public.message.data_error'),
            'spread_deposit.numeric'   => trans('public.message.data_error'),
            'spread_withdraw.required' => trans('public.message.data_error'),
            'spread_withdraw.numeric'  => trans('public.message.data_error'),
        ];
    }
}
