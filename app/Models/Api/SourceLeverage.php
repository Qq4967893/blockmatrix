<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Api\TsSourceLeverage;

class SourceLeverage extends TsSourceLeverage
{
    protected $casts = [
        'commission' => 'float',
        'spread'     => 'int',
        'status'     => 'int',
        'leverage_type' => 'int',
    ];
}
