<?php

namespace App\Models\Et6;

use Scaffold\Framework\Models\TradingSystem\BalanceFlow as ScaffoldBalanceFlow;

class BalanceFlow extends ScaffoldBalanceFlow
{
    public const TRADE_PAY_CMD = 6;
    public const TRADE_PAY_ACTION = 2;
    public const DEPOSIT_WITHDRAW_FEE_CATEGORY = 4;
}
