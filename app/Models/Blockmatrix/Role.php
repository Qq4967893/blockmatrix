<?php

namespace App\Models\Blockmatrix;

use Scaffold\Framework\Models\Model;

class Role extends Model
{
    protected $connection = DB_BM;
    protected $table      = 'role';
    public $timestamps = false;
    protected $guarded    = [];

    public const STATUS_NORMAL = 1; //正常
    public const STATUS_DELETE = 0; //删除
}
