<?php

namespace App\Http\Requests\IbAccount;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class IbAccountListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_code' => ['nullable', 'integer', 'min:1'],
            'ib_id' => ['nullable', 'string', 'max:64'],
            'parent_ib_id' => ['nullable', 'string', 'max:64'],
            'level_name' => ['nullable', 'string', 'max:64'],
            'commission_status' => ['nullable', 'integer'],
            'user_type' => ['nullable', 'string', 'max:32'],
            'protection_status' => ['nullable', 'integer', Rule::in([0, 1])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_code.integer' => trans('public.message.data_error'),
            'user_code.min' => trans('public.message.data_error'),
            'ib_id.string' => trans('public.message.data_error'),
            'ib_id.max' => trans('public.message.data_error'),
            'parent_ib_id.string' => trans('public.message.data_error'),
            'parent_ib_id.max' => trans('public.message.data_error'),
            'level_name.string' => trans('public.message.data_error'),
            'level_name.max' => trans('public.message.data_error'),
            'commission_status.integer' => trans('public.message.data_error'),
            'user_type.string' => trans('public.message.data_error'),
            'user_type.max' => trans('public.message.data_error'),
            'protection_status.integer' => trans('public.message.data_error'),
            'protection_status.in' => trans('public.message.data_error'),
            'limit.integer' => trans('public.message.data_error'),
            'limit.min' => trans('public.message.data_error'),
            'limit.max' => trans('public.message.data_error'),
            'offset.integer' => trans('public.message.data_error'),
            'offset.min' => trans('public.message.data_error'),
        ];
    }
}
