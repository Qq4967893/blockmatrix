<?php

namespace App\Http\Requests\Currency;

use App\Http\Requests\ApiRequest;

class CurrencyListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency' => ['nullable', 'string', 'max:16'],
            'limit'    => ['nullable', 'integer', 'min:1', 'max:200'],
            'offset'   => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'currency.string' => trans('public.message.data_error'),
            'currency.max'    => trans('public.message.data_error'),
            'limit.integer'   => trans('public.message.data_error'),
            'limit.min'       => trans('public.message.data_error'),
            'limit.max'       => trans('public.message.data_error'),
            'offset.integer'  => trans('public.message.data_error'),
            'offset.min'      => trans('public.message.data_error'),
        ];
    }
}
