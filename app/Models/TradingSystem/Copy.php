<?php

namespace App\Models\TradingSystem;

use Scaffold\Framework\Models\Model;

class Copy extends Model
{
    public const int STATUS_FOLLOWING = 0;
    public const int STATUS_ENDED    = 1;
}
