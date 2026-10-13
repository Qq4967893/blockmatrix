<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Api\FundingLedger as ScaffoldFundingLedger;
use Scaffold\Framework\Enums\TypeLedger;

class FundingLedger extends ScaffoldFundingLedger
{
    // operation_source：操作来源
    public const OPERATION_SOURCE_SYSTEM = 1;
    public const OPERATION_SOURCE_MANUAL = 2;

    // direction：资金方向
    public const DIRECTION_IN  = 1;
    public const DIRECTION_OUT = -1;

    // 批次 ID 前缀
    public const BATCH_ID_PREFIX = 'fund_flow_';

    // 单次批量处理最大用户数
    public const MAX_BATCH_USER_COUNT = 10;

    /**
     * 资金管理列表允许筛选的操作来源。
     *
     * @return int[]
     */
    public static function operationSourceOptions(): array
    {
        return [
            self::OPERATION_SOURCE_SYSTEM,
            self::OPERATION_SOURCE_MANUAL,
        ];
    }

    /**
     * 资金管理列表允许筛选的方向。
     *
     * @return int[]
     */
    public static function directionOptions(): array
    {
        return [
            self::DIRECTION_IN,
            self::DIRECTION_OUT,
        ];
    }

    /**
     * 资金管理列表默认支持的资金类型。
     *
     * @return int[]
     */
    public static function managementTypeOptions(): array
    {
        return [
            TypeLedger::NegativeBalance->value,
            TypeLedger::Bonus->value,
            TypeLedger::RedPacket->value,
            TypeLedger::FundsRefund->value,
            TypeLedger::AssetTransfer->value,
        ];
    }
}
