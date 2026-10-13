<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Model;
use Scaffold\FluxWallet\Facades\FluxWallet;

class FundingNetworkConfig extends Model
{
    /** Walletd 返回的路由状态不满足操作前置条件错误码 */
    public const WALLET_ERROR_INVALID_STATE = 'NETWORK_ROUTE_INVALID_STATE';

    public const STATUS_NOT_ENABLED = 'not_enabled';
    public const STATUS_ENABLED = 'enabled';
    public const STATUS_DISABLED = 'disabled';

    public const CONFIG_TYPE_WITHDRAWAL = FluxWallet::OperationWithdrawal;
    public const CONFIG_TYPE_DEPOSIT = FluxWallet::OperationDeposit;

    public const CONFIG_TYPES = [
        self::CONFIG_TYPE_WITHDRAWAL,
        self::CONFIG_TYPE_DEPOSIT,
    ];

    public const STATUSES = [
        self::STATUS_ENABLED,
        self::STATUS_DISABLED,
    ];

    public static function configTypeOptions(): array
    {
        return array_map(
            static fn (string $code): array => [
                'code' => $code,
                'name' => trans('funding_network_config.config_type.' . $code),
            ],
            self::CONFIG_TYPES
        );
    }

    public static function statusOptions(): array
    {
        return array_map(
            static fn (string $code): array => [
                'code' => $code,
                'name' => trans('funding_network_config.status.' . $code),
            ],
            self::STATUSES
        );
    }

    public static function configTypeTextMap(): array
    {
        return [
            self::CONFIG_TYPE_WITHDRAWAL => trans('funding_network_config.config_type.' . self::CONFIG_TYPE_WITHDRAWAL),
            self::CONFIG_TYPE_DEPOSIT => trans('funding_network_config.config_type.' . self::CONFIG_TYPE_DEPOSIT),
        ];
    }

    public static function statusTextMap(): array
    {
        return [
            self::STATUS_ENABLED => trans('funding_network_config.status.' . self::STATUS_ENABLED),
            self::STATUS_NOT_ENABLED => trans('funding_network_config.status.' . self::STATUS_NOT_ENABLED),
            self::STATUS_DISABLED => trans('funding_network_config.status.' . self::STATUS_DISABLED),
        ];
    }
}
