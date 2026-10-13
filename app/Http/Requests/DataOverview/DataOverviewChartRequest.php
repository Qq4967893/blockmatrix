<?php

namespace App\Http\Requests\DataOverview;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class DataOverviewChartRequest extends ApiRequest
{
    public const CHART_TYPES = [
        'daily_registered_users',
        'daily_first_deposit_users',
        'daily_trading_users',
        'daily_trading_lots',
        'daily_trading_amount',
        'daily_order_count',
        'daily_deposit_amount',
        'daily_withdrawal_amount',
        'daily_net_deposit_amount',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_start'         => ['required', 'date', 'date_format:Y-m-d'],
            'date_end'           => ['required', 'date', 'date_format:Y-m-d'],
            'trade_account_type' => ['required', 'string', Rule::in(DataOverviewRequest::TRADE_ACCOUNT_TYPES)],
            'customer_type'      => ['required', 'string', Rule::in(DataOverviewRequest::CUSTOMER_TYPES)],
            'chart_date_type'    => ['required', 'string', Rule::in(DataOverviewRequest::CHART_DATE_TYPES)],
            'chart_type'         => ['required', 'string', Rule::in(self::CHART_TYPES)],
        ];
    }

    public function messages(): array
    {
        return [
            'date_start.required'         => trans('public.message.data_error'),
            'date_start.date'             => trans('public.message.data_error'),
            'date_start.date_format'      => trans('public.message.data_error'),
            'date_end.required'           => trans('public.message.data_error'),
            'date_end.date'               => trans('public.message.data_error'),
            'date_end.date_format'        => trans('public.message.data_error'),
            'trade_account_type.required' => trans('public.message.data_error'),
            'trade_account_type.string'   => trans('public.message.data_error'),
            'trade_account_type.in'       => trans('public.message.data_error'),
            'customer_type.required'      => trans('public.message.data_error'),
            'customer_type.string'        => trans('public.message.data_error'),
            'customer_type.in'            => trans('public.message.data_error'),
            'chart_date_type.required'    => trans('public.message.data_error'),
            'chart_date_type.string'      => trans('public.message.data_error'),
            'chart_date_type.in'          => trans('public.message.data_error'),
            'chart_type.required'         => trans('public.message.data_error'),
            'chart_type.string'           => trans('public.message.data_error'),
            'chart_type.in'               => trans('public.message.data_error'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $dateStart = $this->input('date_start');
            $dateEnd = $this->input('date_end');

            if ($dateStart && $dateEnd && $dateStart > $dateEnd) {
                $validator->errors()->add('date_start', trans('public.message.data_error'));
            }
        });
    }
}
