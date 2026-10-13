<?php

namespace App\Http\Requests\DataOverview;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class DataOverviewRequest extends ApiRequest
{
    /** 品种排行维度：交易手数 */
    public const TRADING_TYPE_LOT         = 'lot';
    /** 品种排行维度：交易金额 */
    public const TRADING_TYPE_AMOUNT      = 'amount';
    /** 品种排行维度：订单数 */
    public const TRADING_TYPE_ORDER_COUNT = 'order_count';

    public const TRADING_TYPES = [
        self::TRADING_TYPE_LOT,
        self::TRADING_TYPE_AMOUNT,
        self::TRADING_TYPE_ORDER_COUNT,
    ];

    /** 交易账户类型：非赠金账户 */
    public const TRADE_ACCOUNT_TYPE_REAL  = 'real';
    /** 交易账户类型：赠金账户 */
    public const TRADE_ACCOUNT_TYPE_TRIAL = 'trial';

    public const TRADE_ACCOUNT_TYPES = [
        self::TRADE_ACCOUNT_TYPE_REAL,
        self::TRADE_ACCOUNT_TYPE_TRIAL,
    ];

    /** 客户类型：全部 */
    public const CUSTOMER_TYPE_ALL   = 'all';
    /** 客户类型：代理客户 */
    public const CUSTOMER_TYPE_AGENT = 'ib';

    public const CUSTOMER_TYPES = [
        self::CUSTOMER_TYPE_ALL,
        self::CUSTOMER_TYPE_AGENT,
    ];

    /** 图表日期粒度：日 */
    public const CHART_DATE_TYPE_DAY   = 'day';
    /** 图表日期粒度：周 */
    public const CHART_DATE_TYPE_WEEK  = 'week';
    /** 图表日期粒度：月 */
    public const CHART_DATE_TYPE_MONTH = 'month';

    public const CHART_DATE_TYPES = [
        self::CHART_DATE_TYPE_DAY,
        self::CHART_DATE_TYPE_WEEK,
        self::CHART_DATE_TYPE_MONTH,
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
            'trade_account_type'  => ['required', 'string', Rule::in(self::TRADE_ACCOUNT_TYPES)],
            'customer_type'       => ['required', 'string', Rule::in(self::CUSTOMER_TYPES)],
            'source_trading_type' => ['required', 'string', Rule::in(self::TRADING_TYPES)],
            'chart_date_type'     => ['required', 'string', Rule::in(self::CHART_DATE_TYPES)],
        ];
    }

    public function messages(): array
    {
        return [
            'date_start.required'     => trans('public.message.data_error'),
            'date_start.date'         => trans('public.message.data_error'),
            'date_start.date_format'  => trans('public.message.data_error'),
            'date_end.required'       => trans('public.message.data_error'),
            'date_end.date'           => trans('public.message.data_error'),
            'date_end.date_format'    => trans('public.message.data_error'),
            'trade_account_type.required'  => trans('public.message.data_error'),
            'trade_account_type.string'    => trans('public.message.data_error'),
            'trade_account_type.in'        => trans('public.message.data_error'),
            'customer_type.required'       => trans('public.message.data_error'),
            'customer_type.string'         => trans('public.message.data_error'),
            'customer_type.in'             => trans('public.message.data_error'),
            'source_trading_type.required' => trans('public.message.data_error'),
            'source_trading_type.string'   => trans('public.message.data_error'),
            'source_trading_type.in'       => trans('public.message.data_error'),
            'chart_date_type.required'     => trans('public.message.data_error'),
            'chart_date_type.string'       => trans('public.message.data_error'),
            'chart_date_type.in'           => trans('public.message.data_error'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $dateStart = $this->input('date_start');
            $dateEnd   = $this->input('date_end');

            if ($dateStart && $dateEnd && $dateStart > $dateEnd) {
                $validator->errors()->add('date_start', trans('public.message.data_error'));
            }
        });
    }
}
