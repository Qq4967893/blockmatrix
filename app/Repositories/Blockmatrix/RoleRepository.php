<?php

namespace App\Repositories\Blockmatrix;

use Scaffold\Framework\Repositories\Repository;
use App\Models\Blockmatrix\Role;

class RoleRepository extends Repository
{

    public function __construct(
        Role $model,
    ) {
        parent::__construct($model);
    }

    /**
     * 角色列表
     *
     * @param int    $parentId 父类id
     * @param string $name     角色名称
     * @param int    $status   状态
     * @param int    $areaId   大区id
     * @param int    $limit    每页条数
     * @param int    $offset   偏移量
     *
     * @return array
     */
    public function getList($parentId, $name, $status, $areaId = null, $limit = null, $offset = null)
    {
        $query = $this->getQuery()->select(['role.id', 'role.parent_id', 'role.name'])
            ->leftJoin('role_area', 'role.id', '=', 'role_area.role_id');
        if (!empty($name)) {
            $query->where('role.name', '=', $name);
        }
        if (!empty($parentId)) {
            $query->where('role.parent_id', '=', $parentId);
        }
        if (!empty($status)) {
            $query->where('role.status', '=', $status);
        }
        if (!empty($areaId)) {
            $query->where('role_area.area_id', '=', $areaId);
        }
        $count = $query->distinct('role.id')->count();
        if ($limit) {
            $data = $query->orderBy('role.id')->groupBy('role.id')->take($limit)->skip($offset)->get()->toArray();
        } else {
            $data = $query->orderBy('role.id')->groupBy('role.id')->get()->toArray();
        }

        return [$data, $count];
    }

    /**
     * 通过id获取记录
     *
     * @param int    $id     主键id
     * @param string $column 查询字段
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|mixed|object|null
     */
    public function getById($id, $column = '')
    {
        if (empty($column)) {
            return $this->getQuery()->where('id', $id)->first();
        }

        return $this->getQuery()->where('id', $id)->value($column);
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
     * 添加角色记录并获取新增id
     *
     * @param array $data 数据
     *
     * @return int
     */
    public function save($data)
    {
        return $this->getQuery()->insertGetId($data);
    }

    /**
     * 通过父id获取角色
     *
     * @param int $parentId 上级角色id
     *
     * @return array
     */
    public function getByParentId($parentId)
    {
        return $this->getQuery()->where('parent_id', '=', $parentId)
            ->where('status', '=', Role::STATUS_NORMAL)
            ->pluck('id')->toArray();
    }

    /**
     * 通过id批量修改状态
     *
     * @param int $ids    id数组
     * @param int $status 状态
     *
     * @return void
     */
    public function updateStatusByIds($ids, $status)
    {
        $this->getQuery()->whereIn('id', $ids)->update(['status' => $status]);
    }

    /**
     * 获取所有的角色名称
     *
     * @return array
     */
    public function getAllRoleNames()
    {
        return $this->getQuery()->where('status', Role::STATUS_NORMAL)->pluck('name', 'id')->toArray();
    }

    /**
     * 检查角色是否有管理员权限
     *
     * @param int $roleId 角色id
     *
     * @return bool
     */
    public function isRoot($roleId)
    {
        if (in_array($roleId, json_decode(config('blockmatrix.role.root'), true))) {
            return true;
        }

        return false;
    }
}

