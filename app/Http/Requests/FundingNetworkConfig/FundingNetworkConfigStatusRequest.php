<?php

namespace App\Http\Requests\FundingNetworkConfig;

use App\Http\Requests\ApiRequest;
use App\Models\Api\FundingNetworkConfig;
use Illuminate\Validation\Rule;

class FundingNetworkConfigStatusRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                FundingNetworkConfig::STATUS_ENABLED,
                FundingNetworkConfig::STATUS_DISABLED,
            ])],
        ];
    }
}
