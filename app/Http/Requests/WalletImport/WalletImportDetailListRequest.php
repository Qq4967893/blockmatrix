<?php

namespace App\Http\Requests\WalletImport;

use App\Http\Requests\ApiRequest;
use App\Models\Api\FundingOperationRecord;
use Illuminate\Validation\Rule;

class WalletImportDetailListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'integer', Rule::in(FundingOperationRecord::statusOptions())],
            'user_code' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.integer' => trans('public.message.data_error'),
            'status.in' => trans('public.message.data_error'),
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.min' => trans('public.message.data_error'),
            'limit.integer' => trans('public.message.data_error'),
            'limit.min' => trans('public.message.data_error'),
            'limit.max' => trans('public.message.data_error'),
            'offset.integer' => trans('public.message.data_error'),
            'offset.min' => trans('public.message.data_error'),
        ];
    }
}
