<?php

namespace App\Http\Requests\PointRule;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use App\Models\Api\PointRule;

class PointRuleStatusRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 修改积分规则状态参数
     *
     * body:
     * - status:int 状态（-1=待启用，1=启用，0=停用）；停用后不可再修改状态
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'integer',
                Rule::in([PointRule::STATUS_PENDING, PointRule::STATUS_ENABLED, PointRule::STATUS_DISABLED]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => trans('public.message.in_complete'),
            'status.integer'  => trans('public.message.type_error'),
            'status.in'       => trans('public.message.wrong_options'),
        ];
    }

    public function expectsJson(): bool
    {
        return true;
    }
}
