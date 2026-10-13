<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Model;

class UserSocial extends Model
{
    protected $connection = DB_API;
    protected $table      = 'user_social';
    public $timestamps    = false;
    protected $guarded    = [];

    public const STATUS_UNBOUND = 0;
    public const STATUS_BOUND = 1;

    // 绑定状态：已解绑，已绑定
    /** @deprecated 使用 STATUS_UNBOUND / STATUS_BOUND 常量 */
    public static $status = ['unbound' => 0, 'bound' => 1];
}
