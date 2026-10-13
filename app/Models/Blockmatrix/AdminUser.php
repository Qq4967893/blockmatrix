<?php

namespace App\Models\Blockmatrix;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Auth\Authenticatable;
use Scaffold\Framework\Models\Model;

class AdminUser extends Model implements AuthenticatableContract
{
    use Authenticatable;
    protected $connection = DB_BM;
    protected $table = 'admin_user';
    public $timestamps = false;

    protected $hidden = [
        'password',
        'remember_token'
    ];

    public const STATUS_NORMAL = 1;  //正常使用状态
    public const STATUS_DELETE = 0; //已删除状态
    public const SYS_ADMIN = 'sysAdmin';
}
