<?php

namespace App\Http\Requests\IbAccount;

use App\Http\Requests\ApiRequest;
use Closure;

class IbAccountLevelUpdateRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ib_id' => ['nullable', 'string'],
            'ib_ids' => ['nullable', 'array', 'min:1'],
            'ib_ids.*' => [
                'distinct',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_scalar($value)) {
                        $fail(trans('public.message.data_error'));
                    }
                },
            ],
            'level_id' => ['required', 'integer', 'min:1'],
            'level_protection_started_at' => ['nullable', 'date'],
            'level_protection_ended_at' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'ib_id.string' => trans('public.message.data_error'),
            'ib_ids.array' => trans('public.message.data_error'),
            'ib_ids.min' => trans('public.message.data_error'),
            'ib_ids.*.distinct' => trans('public.message.data_error'),
            'level_id.required' => trans('public.message.data_error'),
            'level_id.integer' => trans('public.message.data_error'),
            'level_id.min' => trans('public.message.data_error'),
            'level_protection_started_at.date' => trans('public.message.data_error'),
            'level_protection_ended_at.date' => trans('public.message.data_error'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->filled('ib_id') && ! $this->filled('ib_ids')) {
                $validator->errors()->add('ib_id', trans('public.message.in_complete'));
            }

            if ($this->filled('ib_id') && $this->filled('ib_ids')) {
                $validator->errors()->add('ib_id', trans('public.message.data_error'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        foreach (['ib_id', 'level_protection_started_at', 'level_protection_ended_at'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
