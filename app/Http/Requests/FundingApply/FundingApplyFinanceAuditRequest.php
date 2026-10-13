<?php

namespace App\Http\Requests\FundingApply;

use App\Http\Requests\ApiRequest;
use App\Models\Api\FundingApply;

class FundingApplyFinanceAuditRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'user_code' => ['required', 'integer', 'min:1'],
            'hash' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => trans('public.message.data_error'),
            'id.integer' => trans('public.message.data_error'),
            'id.min' => trans('public.message.data_error'),
            'user_code.required' => trans('public.message.data_error'),
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.min' => trans('public.message.data_error'),
            'platform.string' => trans('public.message.data_error'),
            'platform.max' => trans('public.message.data_error'),
            'result.required' => trans('public.message.data_error'),
            'result.string' => trans('public.message.data_error'),
            'result.in' => trans('public.message.data_error'),
            'reason.required_if' => trans('public.message.data_error'),
            'reason.string' => trans('public.message.data_error'),
            'reason.max' => trans('public.message.data_error'),
        ];
    }
}
