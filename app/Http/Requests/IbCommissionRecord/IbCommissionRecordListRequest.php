<?php

namespace App\Http\Requests\IbCommissionRecord;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Enums\TypeIbSettlementStatus;

class IbCommissionRecordListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'close_time_start' => ['nullable', 'date'],
            'close_time_end' => ['nullable', 'date'],
            'settled_at_start' => ['nullable', 'date'],
            'settled_at_end' => ['nullable', 'date'],
            'settlement_status' => ['nullable', 'integer', Rule::in([TypeIbSettlementStatus::Pending->value, TypeIbSettlementStatus::Settled->value, TypeIbSettlementStatus::Rejected->value])],
            'limit' => ['nullable', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'export' => ['nullable', 'boolean'],
            'export_ext' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'close_time_start.date' => trans('public.message.data_error'),
            'close_time_end.date' => trans('public.message.data_error'),
            'settled_at_start.date' => trans('public.message.data_error'),
            'settled_at_end.date' => trans('public.message.data_error'),
            'settlement_status.integer' => trans('public.message.data_error'),
            'settlement_status.in' => trans('public.message.data_error'),
            'limit.integer' => trans('public.message.data_error'),
            'limit.min' => trans('public.message.data_error'),
            'offset.integer' => trans('public.message.data_error'),
            'offset.min' => trans('public.message.data_error'),
            'export.boolean' => trans('public.message.data_error'),
        ];
    }
}
