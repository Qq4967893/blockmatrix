<?php

namespace App\Http\Requests\CreditFlow;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Enums\TypeCreditLedger;
use Scaffold\Framework\Models\Api\UserCreditRecord;

class CreditFlowListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trade_ticket' => ['nullable', 'string', 'max:64'],
            'created_start' => ['nullable', 'date'],
            'created_end' => ['nullable', 'date'],
            'fund_type' => ['nullable', 'integer', Rule::in(array_column(TypeCreditLedger::cases(), 'value'))],
            'credit_type' => ['nullable', 'integer', Rule::in([UserCreditRecord::GENERAL, UserCreditRecord::RESTRICTED])],
            'limit' => ['nullable', 'integer', 'min:0', 'max:100'],
            'offset' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'trade_ticket.string' => trans('public.message.data_error'),
            'trade_ticket.max' => trans('public.message.data_error'),
            'created_start.date' => trans('public.message.wrong_format_date'),
            'created_end.date' => trans('public.message.wrong_format_date'),
            'fund_type.in' => trans('public.message.data_error'),
            'credit_type.in' => trans('public.message.data_error'),
            'limit.integer' => trans('public.message.data_error'),
            'limit.min' => trans('public.message.data_error'),
            'limit.max' => trans('public.message.data_error'),
            'offset.integer' => trans('public.message.data_error'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $start = $this->input('created_start');
            $end = $this->input('created_end');

            if ($start && $end && strtotime($start) > strtotime($end)) {
                $validator->errors()->add('created_start', trans('public.message.wrong_time_select'));
            }
        });
    }
}
