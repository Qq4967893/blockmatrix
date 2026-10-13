<?php

namespace App\Http\Requests\FundingNetworkConfig;

use App\Http\Requests\ApiRequest;

class FundingNetworkConfigUpdateRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('channel')) {
            $this->merge([
                'channel' => trim((string) $this->input('channel')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', 'max:128'],
        ];
    }
}
