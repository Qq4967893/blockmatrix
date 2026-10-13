<?php

namespace App\Http\Requests\IbLevel;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class IbLevelStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $langKeys = array_keys(config('lang.languages', []));

        return [
            'locale_configs' => ['required', 'array', 'min:1'],
            'locale_configs.*.lang' => ['required', 'string', Rule::in($langKeys)],
            'locale_configs.*.name' => ['required', 'string'],
            'commission_rule_id' => ['required', 'integer', 'min:1'],
            'is_initial' => ['nullable', 'boolean'],
            'active_trader_threshold' => ['required', 'integer', 'min:0'],
            'team_position_size_threshold' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'locale_configs.required' => trans('public.message.in_complete'),
            'locale_configs.array' => trans('public.message.data_error'),
            'locale_configs.min' => trans('public.message.in_complete'),
            'locale_configs.*.lang.required' => trans('public.message.in_complete'),
            'locale_configs.*.lang.in' => trans('public.message.lang_err'),
            'locale_configs.*.name.required' => trans('public.message.in_complete'),
            'commission_rule_id.required' => trans('public.message.data_error'),
            'commission_rule_id.integer' => trans('public.message.data_error'),
            'commission_rule_id.min' => trans('public.message.data_error'),
            'is_initial.boolean' => trans('public.message.data_error'),
            'active_trader_threshold.required' => trans('public.message.data_error'),
            'active_trader_threshold.integer' => trans('public.message.data_error'),
            'active_trader_threshold.min' => trans('public.message.data_error'),
            'team_position_size_threshold.required' => trans('public.message.data_error'),
            'team_position_size_threshold.numeric' => trans('public.message.data_error'),
            'team_position_size_threshold.min' => trans('public.message.data_error'),
        ];
    }
}
