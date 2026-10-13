<?php

namespace App\Http\Requests\BalanceFlow;

use App\Constants\BalanceFlowFeeCategory;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Enums\TypeTradeAccount;

class BalanceFlowListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trade_ticket'  => ['nullable', 'string', 'max:64'],
            'created_start' => ['nullable', 'date'],
            'created_end'   => ['nullable', 'date'],
            'fund_type'     => ['nullable', 'integer', Rule::in(BalanceFlowFeeCategory::VISIBLE)],
            'fund_sub_type' => ['nullable', 'integer', Rule::in(BalanceFlowFeeCategory::VISIBLE_SUB_TYPES)],
            'login'         => ['nullable', 'integer'],
            'account_type'  => ['nullable', 'integer', Rule::in(array_map(
                static fn (TypeTradeAccount $type) => $type->value,
                TypeTradeAccount::cases()
            ))],
            'limit'         => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset'        => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'trade_ticket.string'  => trans('public.message.data_error'),
            'trade_ticket.max'     => trans('public.message.data_error'),
            'created_start.date'   => trans('public.message.wrong_format_date'),
            'created_end.date'     => trans('public.message.wrong_format_date'),
            'fund_type.in'         => trans('public.message.data_error'),
            'fund_sub_type.in'     => trans('public.message.data_error'),
            'limit.integer'        => trans('public.message.data_error'),
            'limit.min'            => trans('public.message.data_error'),
            'limit.max'            => trans('public.message.data_error'),
            'offset.integer'       => trans('public.message.data_error'),
            'offset.min'           => trans('public.message.data_error'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $start = $this->input('created_start');
            $end   = $this->input('created_end');

            if ($start && $end && strtotime($start) > strtotime($end)) {
                $validator->errors()->add('created_start', trans('public.message.wrong_time_select'));
            }
        });
    }
}
