<?php

namespace App\Http\Requests\IbCommissionRule;

use App\Http\Requests\ApiRequest;
use App\Repositories\Api\IbCommissionRuleRepository;
use Illuminate\Validation\Rule;

class IbCommissionRuleReviewRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'integer', Rule::in([IbCommissionRuleRepository::REVIEW_ACTION_APPROVE, IbCommissionRuleRepository::REVIEW_ACTION_REJECT])],
            'rejection_reason' => ['nullable', 'required_if:action,' . IbCommissionRuleRepository::REVIEW_ACTION_REJECT, 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => trans('public.message.data_error'),
            'action.integer' => trans('public.message.data_error'),
            'action.in' => trans('public.message.data_error'),
            'rejection_reason.required_if' => trans('public.message.in_complete'),
            'rejection_reason.max' => trans('public.message.data_error'),
        ];
    }
}
