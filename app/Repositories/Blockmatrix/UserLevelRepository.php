<?php

namespace App\Repositories\Blockmatrix;

use App\Models\Blockmatrix\UserLevel;
use Scaffold\Framework\Repositories\Repository;

class UserLevelRepository extends Repository
{
    public function __construct(UserLevel $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取所有用户层级
     *
     * @return array
     */
    public function getList()
    {
        return $this->getQuery()->leftJoin('user_level as a', 'a.id', '=', 'user_level.parent_id')
            ->select(['user_level.*','a.name as pname'])->get()->toArray();
    }

    /**
     * 通过id批量获取数据
     *
     * @param array  $ids    id数组
     * @param string $column 字段
     *
     * @return array
     */
    public function getColumnByIds($ids, $column)
    {
        return $this->getQuery()->whereIn('id', $ids)->pluck($column, 'id')->toArray();
    }

    /**
     * 根据层级类型批量获取id
     *
     * @param int $type 层级类型(1:内部层级; 2:外部层级)
     *
     * @return array
     */
    public function getIdsByType($type)
    {
        return $this->getQuery()->where('type', $type)->pluck('id')->toArray();
    }
}
