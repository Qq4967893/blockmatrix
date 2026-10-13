<?php

namespace App\Http\Requests\FundingApply;

use App\Http\Requests\ApiRequest;

class FundingApplyRiskRejectRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_no' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_no.required' => trans('public.message.data_error'),
            'order_no.integer' => trans('public.message.data_error'),
            'order_no.min' => trans('public.message.data_error'),
            'reason.required' => trans('public.message.data_error'),
            'reason.string' => trans('public.message.data_error'),
            'reason.max' => trans('public.message.data_error'),
        ];
    }
}
