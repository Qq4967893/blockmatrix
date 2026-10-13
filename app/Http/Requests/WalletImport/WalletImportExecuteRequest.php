<?php

namespace App\Http\Requests\WalletImport;

use App\Http\Requests\ApiRequest;

class WalletImportExecuteRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'activity_name' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => trans('public.message.data_error'),
            'id.integer' => trans('public.message.data_error'),
            'id.min' => trans('public.message.data_error'),
            'activity_name.string' => trans('public.message.data_error'),
            'activity_name.max' => trans('public.message.data_error'),
            'reason.string' => trans('public.message.data_error'),
            'reason.max' => trans('public.message.data_error'),
        ];
    }
}
