<?php

namespace App\Repositories\Api;

use App\Models\Api\UserProhibition;
use Scaffold\Framework\Repositories\Repository;

class UserProhibitionRepository extends Repository
{
    public function __construct(UserProhibition $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取用户权限设置
     *
     * @param array $userCodes 用户ID
     *
     * @return array
     */
    public function getAll($userCodes){
        return $this->getQuery()->whereIn('user_code', $userCodes)->get()->toArray();
    }

    /**
     * 用户权限是否存在
     *
     * @param int $userCode 用户ID
     * @param int $type
     *
     * @return bool
     */
    public function isExists($userCode, $type){
        return $this->getQuery()->where('user_code', $userCode)->where('type', $type)->exists();
    }
}
