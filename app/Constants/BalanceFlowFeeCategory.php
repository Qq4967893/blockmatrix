<?php

namespace App\Constants;

use Scaffold\Framework\Enums\TypeLedger;

final class BalanceFlowFeeCategory
{
    public const int SETTLEMENT = 3;

    public const int DEPOSIT_WITHDRAWAL = 4;

    public const int DIVIDEND = 5;

    public const int NEGATIVE_BALANCE_COMPENSATION = 6;

    public const int TRADING_FEE = 8;

    public const array VISIBLE = [
        self::SETTLEMENT,
        self::DEPOSIT_WITHDRAWAL,
        self::DIVIDEND,
        self::NEGATIVE_BALANCE_COMPENSATION,
        self::TRADING_FEE,
    ];

    public const int SUB_TYPE_UNLOCK_CREDIT = TypeLedger::UnlockCredit->value;

    public const array VISIBLE_SUB_TYPES = [
        self::SUB_TYPE_UNLOCK_CREDIT,
    ];
}
