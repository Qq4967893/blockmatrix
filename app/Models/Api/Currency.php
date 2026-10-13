<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Model;

class Currency extends Model
{
    protected $connection = DB_API;
    protected $table = 'currency';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'rate'             => 'float',
        'spread_deposit'   => 'float',
        'spread_withdraw'  => 'float',
    ];
}
