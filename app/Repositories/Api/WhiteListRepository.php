<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Repositories\Repository;
use App\Models\Api\User;

class WhiteListRepository extends Repository
{
    public function __construct(
        User $model
    ) {
        parent::__construct($model);
    }

    /**
     * 用户白名单列表
     *
     * @param int    $userCode             用户ID
     * @param string $registerTimeStart    注册开始时间
     * @param string $registerTimeEnd      注册结束时间
     * @param array  $whiteOperateTargetIds 白名单添加记录对应的用户ID
     * @param bool   $filterByWhiteTime    是否需要按白名单添加时间筛选
     * @param int    $offset               偏移量
     * @param int    $limit                每页条数
     *
     * @return array
     */
    public function getList(
        $userCode,
        $registerTimeStart,
        $registerTimeEnd,
        array $whiteOperateTargetIds,
        bool $filterByWhiteTime,
        $offset,
        $limit
    ) {
        $columns = ['id', 'user_code', 'username', 'created_at as register_time', 'type', 'email', 'wallet_address'];
        $query   = $this->getQuery()->select($columns)->where('type', User::TYPE_TEST);
        if ($userCode) {
            $query->where('user_code', $userCode);
        }

        if ($registerTimeStart && $registerTimeEnd) {
            $query->whereBetween('created_at', [$registerTimeStart, $registerTimeEnd]);
        } elseif ($registerTimeStart) {
            $query->where('created_at', '>=', $registerTimeStart);
        } elseif ($registerTimeEnd) {
            $query->where('created_at', '<=', $registerTimeEnd);
        }

        if ($filterByWhiteTime) {
            $query->whereIn('id', $whiteOperateTargetIds);
        }

        $count = $query->count();
        $data  = $query->orderBy('id', 'desc')->offset($offset)->limit($limit)->get()->toArray();

        return [$data, $count];
    }
}
