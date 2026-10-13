<?php

namespace App\Http\Requests\AppRelease;

use App\Http\Requests\ApiRequest;

class AppReleaseListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 客户端版本列表参数（当前无查询参数）
     */
    public function rules(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [];
    }
}
