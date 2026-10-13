<?php

namespace App\Http\Requests\Point;

use App\Http\Requests\ApiRequest;

class UserPointLogListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code' => ['nullable', 'integer', 'min:1'],
            'start_at'  => ['nullable', 'date'],
            'end_at'    => ['nullable', 'date'],
            'limit'     => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset'    => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.min'     => trans('public.message.data_error'),
            'start_at.date'     => trans('public.message.wrong_format_date'),
            'end_at.date'       => trans('public.message.wrong_format_date'),
            'limit.integer'     => trans('public.message.data_error'),
            'limit.min'         => trans('public.message.data_error'),
            'limit.max'         => trans('public.message.data_error'),
            'offset.integer'    => trans('public.message.data_error'),
            'offset.min'        => trans('public.message.data_error'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $start = $this->input('start_at');
            $end   = $this->input('end_at');
            if ($start && $end && strtotime($start) > strtotime($end)) {
                $validator->errors()->add('start_at', trans('public.message.wrong_time_select'));
            }
        });
    }
}
