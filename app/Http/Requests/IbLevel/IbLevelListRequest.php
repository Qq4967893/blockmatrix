<?php

namespace App\Http\Requests\IbLevel;

use App\Http\Requests\ApiRequest;

class IbLevelListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'level_pos' => ['nullable', 'integer', 'min:1'],
            'name' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'level_pos.integer' => trans('public.message.data_error'),
            'level_pos.min' => trans('public.message.data_error'),
            'limit.integer' => trans('public.message.data_error'),
            'limit.min' => trans('public.message.data_error'),
            'offset.integer' => trans('public.message.data_error'),
            'offset.min' => trans('public.message.data_error'),
        ];
    }
}
