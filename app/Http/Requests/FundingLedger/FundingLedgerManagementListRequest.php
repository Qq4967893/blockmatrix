<?php

namespace App\Http\Requests\FundingLedger;

use App\Http\Requests\ApiRequest;
use App\Models\Api\FundingLedger;
use Illuminate\Validation\Rule;

/**
 * 资金管理列表请求参数校验。
 */
class FundingLedgerManagementListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 约束资金管理列表的筛选参数格式和值域。
     */
    public function rules(): array
    {
        return [
            'user_code'         => ['nullable', 'integer', 'min:1'],
            'operation_source'  => ['nullable', 'integer', Rule::in(FundingLedger::operationSourceOptions())],
            'direction'         => ['nullable', 'integer', Rule::in(FundingLedger::directionOptions())],
            'created_start'     => ['nullable', 'date'],
            'created_end'       => ['nullable', 'date'],
            'fund_type'         => ['nullable', 'integer', Rule::in(FundingLedger::managementTypeOptions())],
            'limit'             => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset'            => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * 统一返回项目内通用的校验错误文案。
     */
    public function messages(): array
    {
        return [
            'user_code.integer'        => trans('public.message.data_error'),
            'user_code.min'            => trans('public.message.data_error'),
            'operation_source.integer' => trans('public.message.data_error'),
            'operation_source.in'      => trans('public.message.data_error'),
            'direction.integer'        => trans('public.message.data_error'),
            'direction.in'             => trans('public.message.data_error'),
            'created_start.date'       => trans('public.message.wrong_format_date'),
            'created_end.date'         => trans('public.message.wrong_format_date'),
            'fund_type.integer'        => trans('public.message.data_error'),
            'fund_type.in'             => trans('public.message.data_error'),
            'limit.integer'            => trans('public.message.data_error'),
            'limit.min'                => trans('public.message.data_error'),
            'limit.max'                => trans('public.message.data_error'),
            'offset.integer'           => trans('public.message.data_error'),
            'offset.min'               => trans('public.message.data_error'),
        ];
    }

    /**
     * 校验时间区间，确保开始时间不大于结束时间。
     */
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
