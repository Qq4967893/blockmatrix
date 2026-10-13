<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Model;

class IbStatSnapshot extends Model
{
    protected $connection = DB_API;
    protected $table = 'ib_stat_snapshot';
    public $timestamps = false;
    protected $guarded = [];
}
