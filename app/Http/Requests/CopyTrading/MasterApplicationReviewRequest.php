<?php

namespace App\Http\Requests\CopyTrading;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Models\Api\MasterApplication;

class MasterApplicationReviewRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', Rule::in([
                MasterApplication::STATUS_APPROVED,
                MasterApplication::STATUS_REJECTED,
            ])],
            'rejection_reason' => ['nullable', 'required_if:status,' . MasterApplication::STATUS_REJECTED, 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => trans('public.message.data_error'),
            'status.integer' => trans('public.message.data_error'),
            'status.in' => trans('public.message.data_error'),
            'rejection_reason.required_if' => trans('public.message.in_complete'),
            'rejection_reason.string' => trans('public.message.data_error'),
            'rejection_reason.max' => trans('public.message.data_error'),
        ];
    }
}
