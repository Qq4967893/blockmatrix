<?php

namespace App\Http\Requests\IbApplication;

use App\Http\Requests\ApiRequest;

class IbApplicationApproveRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => trans('public.message.data_error'),
            'id.integer' => trans('public.message.data_error'),
            'id.min' => trans('public.message.data_error'),
        ];
    }
}
