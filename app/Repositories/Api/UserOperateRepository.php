<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Repositories\Repository;
use App\Models\Api\User;

class UserOperateRepository extends Repository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    /**
     * 根据用户ID查询客户数据
     *
     * @param int   $user_code 用户ID
     * @param array $columns   字段
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getUserByUserCode($user_code, $columns = ['*'])
    {
        return $this->getQuery()->where('user_code', $user_code)->select($columns)->first();
    }
}
