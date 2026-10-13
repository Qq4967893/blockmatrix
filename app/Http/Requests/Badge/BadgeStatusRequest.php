<?php

namespace App\Http\Requests\Badge;

use App\Http\Requests\ApiRequest;
use App\Models\Api\Badge;
use Illuminate\Validation\Rule;

class BadgeStatusRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'integer', Rule::in([Badge::STATUS_DISABLED, Badge::STATUS_ENABLED])],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => trans('public.message.Incomplete'),
            'status.integer'  => trans('public.message.data_error'),
            'status.in'       => trans('public.message.wrong_options'),
        ];
    }
}
