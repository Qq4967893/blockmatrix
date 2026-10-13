<?php

namespace App\Http\Requests\IbCommissionRule;

use App\Http\Requests\ApiRequest;
use App\Http\Requests\IbCommissionRule\Concerns\ValidatesIbCommissionRulePayload;
use Illuminate\Validation\Rule;

class IbCommissionRuleStoreRequest extends ApiRequest
{
    use ValidatesIbCommissionRulePayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale_configs' => ['required', 'array', 'min:1'],
            'locale_configs.*.lang' => ['required', 'string', Rule::in($this->langKeys())],
            'locale_configs.*.name' => ['required', 'string'],
            'base_commission_rate' => ['required', 'numeric', 'min:0'],
            'promotion_commission_rate' => ['nullable', 'numeric', 'min:0'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.source' => ['required', 'string'],
            'details.*.position_size_threshold' => ['required', 'numeric', 'min:0'],
            'details.*.commission_amount' => ['nullable', 'numeric', 'min:0'],
            'details.*.commission_fee_rate' => ['nullable', 'numeric', 'min:0'],
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
            'base_commission_rate.required' => trans('public.message.in_complete'),
            'base_commission_rate.numeric' => trans('public.message.data_error'),
            'base_commission_rate.min' => trans('public.message.data_error'),
            'promotion_commission_rate.numeric' => trans('public.message.data_error'),
            'promotion_commission_rate.min' => trans('public.message.data_error'),
            'details.required' => trans('public.message.in_complete'),
            'details.array' => trans('public.message.data_error'),
            'details.min' => trans('public.message.in_complete'),
            'details.*.source.required' => trans('public.message.in_complete'),
            'details.*.position_size_threshold.required' => trans('public.message.in_complete'),
            'details.*.position_size_threshold.numeric' => trans('public.message.data_error'),
            'details.*.position_size_threshold.min' => trans('public.message.data_error'),
            'details.*.commission_amount.numeric' => trans('public.message.data_error'),
            'details.*.commission_amount.min' => trans('public.message.data_error'),
            'details.*.commission_fee_rate.numeric' => trans('public.message.data_error'),
            'details.*.commission_fee_rate.min' => trans('public.message.data_error'),
        ];
    }
}
