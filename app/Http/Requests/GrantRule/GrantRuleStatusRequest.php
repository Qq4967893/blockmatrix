<?php

namespace App\Http\Requests\GrantRule;

use App\Http\Requests\ApiRequest;
use App\Models\Api\GrantRule;
use Illuminate\Validation\Rule;

class GrantRuleStatusRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'integer',
                Rule::in(GrantRule::STATUS_UPDATE_OPTIONS),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => trans('grant_rule.validation.status_required'),
            'status.integer' => trans('grant_rule.validation.status_integer'),
            'status.in' => trans('grant_rule.validation.status_in'),
        ];
    }
}
