<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Model;

class MessageTransaction extends Model
{
    protected $connection = DB_API;
    protected $table      = 'message_transaction';
    public $timestamps    = false;
    protected $guarded    = [];
}
