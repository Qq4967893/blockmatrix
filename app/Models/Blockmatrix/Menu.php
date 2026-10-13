<?php

namespace App\Models\Blockmatrix;

use Scaffold\Framework\Models\Model;

class Menu extends Model
{
    protected $connection = DB_BM;
    protected $table      = 'menu';
    public $timestamps = false;
    protected $guarded    = [];

    public const CATEGORY_FUNC = 1; //功能菜单
    public const CATEGORY_SYS  = 0; //系统设置菜单

    public const STATUS_NORMAL = 1; //正常使用菜单
    public const STATUS_DELETE = 0; //已删除菜单
}
