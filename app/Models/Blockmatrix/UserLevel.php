<?php

namespace App\Models\Blockmatrix;

use Scaffold\Framework\Models\Model;

class UserLevel extends Model
{
    protected $connection = DB_BM;
    protected $table      = 'user_level';
    public $timestamps = false;
    protected $guarded    = [];

    //层级类型
    public const TYPE_INSIDE  = 1; //内部层级
    public const TYPE_OUTSIDE = 2; //外部层级

    public const SELLER_LEVEL = [];  //销售
    public const NO_SALES_TEAM = []; //不需要销售团队的层级
}
