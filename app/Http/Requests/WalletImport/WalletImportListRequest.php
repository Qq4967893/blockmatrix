<?php

namespace App\Http\Requests\WalletImport;

use App\Http\Requests\ApiRequest;
use App\Models\Api\FundingOperation;
use App\Models\Api\FundingLedger;
use Illuminate\Validation\Rule;

class WalletImportListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'direction' => ['nullable', 'integer', Rule::in(FundingLedger::directionOptions())],
            'fund_type' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'integer', Rule::in(FundingOperation::listFilterStatusOptions())],
            'created_by' => ['nullable', 'integer', 'min:1'],
            'created_start' => ['nullable', 'date'],
            'created_end' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'direction.integer' => trans('public.message.data_error'),
            'direction.in' => trans('public.message.data_error'),
            'fund_type.integer' => trans('public.message.data_error'),
            'fund_type.min' => trans('public.message.data_error'),
            'status.integer' => trans('public.message.data_error'),
            'status.in' => trans('public.message.data_error'),
            'created_by.integer' => trans('public.message.data_error'),
            'created_by.min' => trans('public.message.data_error'),
            'created_start.date' => trans('public.message.wrong_format_date'),
            'created_end.date' => trans('public.message.wrong_format_date'),
            'limit.integer' => trans('public.message.data_error'),
            'limit.min' => trans('public.message.data_error'),
            'limit.max' => trans('public.message.data_error'),
            'offset.integer' => trans('public.message.data_error'),
            'offset.min' => trans('public.message.data_error'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $start = $this->input('created_start');
            $end = $this->input('created_end');

            if ($start && $end && strtotime($start) > strtotime($end)) {
                $validator->errors()->add('created_start', trans('public.message.wrong_time_select'));
            }
        });
    }
}
