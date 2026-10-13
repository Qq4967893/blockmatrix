<?php

namespace App\Http\Requests\FundingNetworkConfig;

use App\Http\Requests\ApiRequest;
use App\Models\Api\FundingNetworkConfig;
use Illuminate\Validation\Rule;

class FundingNetworkConfigListRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'config_type' => ['nullable', 'string', Rule::in(FundingNetworkConfig::CONFIG_TYPES)],
            'network' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', Rule::in(FundingNetworkConfig::STATUSES)],
            'channel' => ['nullable', 'string', 'max:128'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page_after_id' => ['nullable', 'integer', 'min:0'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
