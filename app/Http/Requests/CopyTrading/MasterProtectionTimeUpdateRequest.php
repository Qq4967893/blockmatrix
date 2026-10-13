<?php

namespace App\Http\Requests\CopyTrading;

use App\Http\Requests\ApiRequest;

class MasterProtectionTimeUpdateRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'trade_account_id' => ['nullable', 'integer', 'min:1'],
            'trade_account_ids' => ['nullable', 'array', 'min:1'],
            'trade_account_ids.*' => ['integer', 'min:1', 'distinct'],
            'level_protection_started_at' => ['nullable', 'date'],
            'level_protection_ended_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'trade_account_id.integer' => trans('public.message.data_error'),
            'trade_account_id.min' => trans('public.message.data_error'),
            'trade_account_ids.array' => trans('public.message.data_error'),
            'trade_account_ids.min' => trans('public.message.data_error'),
            'trade_account_ids.*.integer' => trans('public.message.data_error'),
            'trade_account_ids.*.min' => trans('public.message.data_error'),
            'trade_account_ids.*.distinct' => trans('public.message.data_error'),
            'level_protection_started_at.date' => trans('public.message.wrong_format_date'),
            'level_protection_ended_at.date' => trans('public.message.wrong_format_date'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->filled('trade_account_id') && ! $this->filled('trade_account_ids')) {
                $validator->errors()->add('trade_account_id', trans('public.message.in_complete'));
            }

            if ($this->filled('trade_account_id') && $this->filled('trade_account_ids')) {
                $validator->errors()->add('trade_account_id', trans('public.message.data_error'));
            }

            $startedAt = $this->input('level_protection_started_at');
            $endedAt = $this->input('level_protection_ended_at');
            if ($startedAt !== null && $startedAt !== '' && $endedAt !== null && $endedAt !== '' && strtotime((string) $startedAt) > strtotime((string) $endedAt)) {
                $validator->errors()->add('level_protection_started_at', trans('public.message.wrong_time_select'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        foreach (['trade_account_id', 'level_protection_started_at', 'level_protection_ended_at'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
