<?php

namespace App\Http\Requests\IbApplication;

use App\Http\Requests\ApiRequest;

class IbApplicationRejectRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'rejection_reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => trans('public.message.data_error'),
            'id.integer' => trans('public.message.data_error'),
            'id.min' => trans('public.message.data_error'),
            'rejection_reason.required' => trans('public.message.data_error'),
            'rejection_reason.string' => trans('public.message.data_error'),
            'rejection_reason.max' => trans('public.message.data_error'),
        ];
    }
}
