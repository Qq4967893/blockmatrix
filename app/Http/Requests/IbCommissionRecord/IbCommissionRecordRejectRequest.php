<?php

namespace App\Http\Requests\IbCommissionRecord;

use App\Http\Requests\ApiRequest;

class IbCommissionRecordRejectRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'commission_record_id' => ['required', 'integer', 'min:1'],
            'rejection_reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'commission_record_id.required' => trans('public.message.data_error'),
            'commission_record_id.integer' => trans('public.message.data_error'),
            'commission_record_id.min' => trans('public.message.data_error'),
            'rejection_reason.required' => trans('public.message.data_error'),
            'rejection_reason.string' => trans('public.message.data_error'),
            'rejection_reason.max' => trans('public.message.data_error'),
        ];
    }
}
