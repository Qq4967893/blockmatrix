<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Repositories\Repository;
use App\Models\Api\User;

class BlackListRepository extends Repository
{
    public function __construct(
        User $model
    ) {
        parent::__construct($model);
    }

    /**
     * 获取黑名单列表
     *
     * @param int $userCode 用户ID
     * @param string $email 邮箱
     * @param string $regStartTime 注册开始时间
     * @param string $regEndTime 注册结束时间
     *
     * @return array
     */
    public function getList($userCode, $email, $regStartTime, $regEndTime)
    {
        $columns = ['id', 'user_code', 'created_at as register_time', 'wallet_address', 'email'];
        $query   = $this->getQuery()->select($columns)->where('type', User::TYPE_BLACK);
        if ($userCode) {
            $query->where('user_code', $userCode);
        }

        if ($email) {
            $query->where('email', $email);
        }

        if ($regStartTime && $regEndTime) {
            $query->whereBetween('register_time', [$regStartTime, $regEndTime]);
        }

        $count = $query->count();
        $data  = $query->get()->toArray();

        return [$data, $count];
    }
}
