<?php

namespace App\Http\Requests\FundingNetworkConfig;

use App\Http\Requests\ApiRequest;
use App\Models\Api\FundingNetworkConfig;
use Illuminate\Validation\Rule;

class FundingNetworkConfigStoreRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $configs = $this->input('configs');
        if (! is_array($configs)) {
            return;
        }

        $this->merge([
            'configs' => array_map(static function ($config): array {
                if (! is_array($config)) {
                    return [];
                }

                return [
                    'config_type' => trim((string) ($config['config_type'] ?? '')),
                    'currency' => trim((string) ($config['currency'] ?? '')),
                    'network' => trim((string) ($config['network'] ?? '')),
                    'channel' => trim((string) ($config['channel'] ?? '')),
                ];
            }, $configs),
        ]);
    }

    public function rules(): array
    {
        return [
            'configs' => ['required', 'array', 'min:1', 'max:100'],
            'configs.*.config_type' => ['required', 'string', Rule::in(FundingNetworkConfig::CONFIG_TYPES)],
            'configs.*.currency' => ['required', 'string', 'max:32'],
            'configs.*.network' => ['required', 'string', 'max:64'],
            'configs.*.channel' => ['required', 'string', 'max:128'],
        ];
    }
}
