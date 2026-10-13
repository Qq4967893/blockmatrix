<?php

namespace App\Models\Blockmatrix;

use Scaffold\Framework\Models\Model;

class RoleArea extends Model
{
    protected $connection = DB_BM;
    protected $table      = 'role_area';
    public $timestamps = false;
    protected $guarded    = [];
}
