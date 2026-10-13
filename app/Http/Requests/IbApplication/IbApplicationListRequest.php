<?php

namespace App\Http\Requests\IbApplication;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Enums\TypeIbApplicationStatus;

class IbApplicationListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code' => ['nullable', 'integer', 'min:1'],
            'applied_at_start' => ['nullable', 'date'],
            'applied_at_end' => ['nullable', 'date'],
            'status' => ['nullable', 'integer', Rule::in(array_map(fn ($c) => $c->value, TypeIbApplicationStatus::cases()))],
            'operator_name' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.min' => trans('public.message.data_error'),
            'applied_at_start.date' => trans('public.message.wrong_format_date'),
            'applied_at_end.date' => trans('public.message.wrong_format_date'),
            'status.in' => trans('public.message.data_error'),
            'operator_name.string' => trans('public.message.data_error'),
            'operator_name.max' => trans('public.message.data_error'),
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
            $start = $this->input('applied_at_start');
            $end = $this->input('applied_at_end');

            if ($start && $end && strtotime($start) > strtotime($end)) {
                $validator->errors()->add('applied_at_start', trans('public.message.wrong_time_select'));
            }
        });
    }
}
