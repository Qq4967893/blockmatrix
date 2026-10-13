<?php

namespace App\Http\Requests\Coupon;

use App\Http\Requests\ApiRequest;
use App\Models\Api\Coupon;
use Illuminate\Validation\Rule;

class CouponStatusRequest extends ApiRequest
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
                Rule::in(Coupon::STATUS_UPDATE_OPTIONS),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => trans('coupon.validation.status_required'),
            'status.integer' => trans('coupon.validation.status_integer'),
            'status.in' => trans('coupon.validation.status_in'),
        ];
    }
}
