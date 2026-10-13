<?php

namespace App\Repositories\Blockmatrix;

use Scaffold\Framework\Repositories\Repository;
use App\Models\Blockmatrix\RoleArea;
use Illuminate\Support\Facades\DB;

class RoleAreaRepository extends Repository
{
    public function __construct(RoleArea $model)
    {
        parent::__construct($model);
    }

    /**
     * 批量写入记录
     *
     * @param array $data 要批量保存的数据
     *
     * @return bool
     */
    public function batchInsert($data)
    {
        return $this->getQuery()->insert($data);
    }

    /**
     * 获取角色大区
     *
     * @param int|null $roleId 角色id
     *
     * @return array
     */
    public function getAreaByRoleId($roleId)
    {
        $query = $this->getQuery()->leftJoin('role', 'role.id', '=', 'role_area.role_id');
        if ($roleId) {
            $query->where('role_area.role_id', '=', $roleId);
        }
        return $query->select(['role_area.role_id', 'role_area.area_id'])
            ->orderBy('role_area.area_id')
            ->get()->toArray();
    }

    /**
     * 通过角色id对应的大区数量
     *
     * @return array
     */
    public function getRoleAreaNum()
    {
        return $this->getQuery()->select(DB::raw('role_id,count(id) as num'))->groupBy('role_id')->pluck('num', 'role_id')->toArray();
    }

    /**
     * 根据角色获取大区id
     *
     * @param int $roleId 角色id
     *
     * @return array
     */
    public function getAreaIdsByRoleId($roleId)
    {
        return $this->getQuery()->where('role_id', $roleId)->pluck('area_id')->toArray();
    }
}
