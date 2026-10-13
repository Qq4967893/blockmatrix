<?php

namespace App\Models\Blockmatrix;

use Scaffold\Framework\Models\Model;

class UserMenuFavorite extends Model
{
    protected $connection = DB_BM;
    protected $table      = 'user_menu_favorite';
    public $timestamps = false;
    protected $guarded    = [];
}
