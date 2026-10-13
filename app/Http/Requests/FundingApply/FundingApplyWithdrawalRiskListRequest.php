<?php

namespace App\Http\Requests\FundingApply;

use App\Http\Requests\ApiRequest;
use App\Models\Api\FundingApply;

class FundingApplyWithdrawalRiskListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code' => ['nullable', 'integer', 'min:1'],
            'order_no' => ['nullable', 'integer', 'min:1'],
            'status' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $statusMap = FundingApply::withdrawalRiskStatusMap();

                    if (is_numeric($value)) {
                        if (in_array((int) $value, array_values($statusMap), true)) {
                            return;
                        }
                    } elseif (is_string($value)) {
                        if (array_key_exists(strtolower($value), $statusMap)) {
                            return;
                        }
                    }

                    $fail(trans('public.message.data_error'));
                },
            ],
            'platform' => ['nullable', 'string', 'max:64'],
            'withdraw_time_start' => ['nullable', 'date'],
            'withdraw_time_end' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.min' => trans('public.message.data_error'),
            'order_no.integer' => trans('public.message.data_error'),
            'order_no.min' => trans('public.message.data_error'),
            'status.in' => trans('public.message.data_error'),
            'platform.string' => trans('public.message.data_error'),
            'platform.max' => trans('public.message.data_error'),
            'withdraw_time_start.date' => trans('public.message.wrong_format_date'),
            'withdraw_time_end.date' => trans('public.message.wrong_format_date'),
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
            $start = $this->input('withdraw_time_start');
            $end = $this->input('withdraw_time_end');
            if ($start && $end && strtotime($start) > strtotime($end)) {
                $validator->errors()->add('withdraw_time_start', trans('public.message.wrong_time_select'));
            }
        });
    }
}
