<?php

namespace App\Http\Requests\FundingApply;

use App\Http\Requests\ApiRequest;
use App\Models\Api\FundingApply;
use Illuminate\Validation\Rule;

class FundingApplyListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'integer', Rule::in(FundingApply::DEPOSIT_STATUS_OPTIONS)],
            'platform' => ['nullable', 'string', 'max:64'],
            'deposit_status' => ['nullable', 'string', Rule::in(FundingApply::DEPOSIT_STATUS_FILTER_OPTIONS)],
            'direction' => ['nullable', 'integer', Rule::in(FundingApply::DEPOSIT_DIRECTION_OPTIONS)],
            'received_time_start' => ['nullable', 'date'],
            'received_time_end' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.min' => trans('public.message.data_error'),
            'status.in' => trans('public.message.data_error'),
            'platform.string' => trans('public.message.data_error'),
            'platform.max' => trans('public.message.data_error'),
            'deposit_status.in' => trans('public.message.data_error'),
            'direction.integer' => trans('public.message.data_error'),
            'direction.in' => trans('public.message.data_error'),
            'received_time_start.date' => trans('public.message.wrong_format_date'),
            'received_time_end.date' => trans('public.message.wrong_format_date'),
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
            $start = $this->input('received_time_start');
            $end = $this->input('received_time_end');
            if ($start && $end && strtotime($start) > strtotime($end)) {
                $validator->errors()->add('received_time_start', trans('public.message.wrong_time_select'));
            }
        });
    }
}
