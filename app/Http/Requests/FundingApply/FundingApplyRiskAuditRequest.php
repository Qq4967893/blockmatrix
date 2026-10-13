<?php

namespace App\Http\Requests\FundingApply;

use App\Http\Requests\ApiRequest;
use App\Models\Api\FundingApply;
use Illuminate\Validation\Rule;

class FundingApplyRiskAuditRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'result' => ['required', 'string', Rule::in([FundingApply::RISK_RESULT_APPROVED, FundingApply::RISK_RESULT_REJECTED])],
            'reason' => ['required_if:result,' . FundingApply::RISK_RESULT_REJECTED, 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => trans('public.message.data_error'),
            'id.integer' => trans('public.message.data_error'),
            'id.min' => trans('public.message.data_error'),
            'result.required' => trans('public.message.data_error'),
            'result.string' => trans('public.message.data_error'),
            'result.in' => trans('public.message.data_error'),
            'reason.required_if' => trans('public.message.data_error'),
            'reason.string' => trans('public.message.data_error'),
            'reason.max' => trans('public.message.data_error'),
        ];
    }
}
