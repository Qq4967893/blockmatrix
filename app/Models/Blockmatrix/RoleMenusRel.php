<?php

namespace App\Models\Blockmatrix;

use Scaffold\Framework\Models\Model;


class RoleMenusRel extends Model
{
    protected $connection = DB_BM;
    protected $table      = 'role_menu_rel';
    public $timestamps = false;
    protected $guarded    = [];
}
