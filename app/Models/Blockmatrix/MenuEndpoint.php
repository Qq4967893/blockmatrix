<?php

namespace App\Models\Blockmatrix;

use Scaffold\Framework\Models\Model;

class MenuEndpoint extends Model
{
    protected $connection = DB_BM;

    protected $table = 'menu_endpoint';

    public $timestamps = false;

    protected $guarded = [];
}
