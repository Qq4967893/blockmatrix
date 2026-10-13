<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Model;
class PositionTab extends Model
{
    protected $connection = DB_API;
    protected $table      = 'position_tab';
    public $timestamps = false;
    protected $guarded    = [];
}
