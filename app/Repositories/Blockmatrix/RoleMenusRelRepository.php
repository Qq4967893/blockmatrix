<?php

namespace App\Repositories\Blockmatrix;

use Scaffold\Framework\Repositories\Repository;
use App\Models\Blockmatrix\RoleMenusRel;
use App\Models\Blockmatrix\Menu;

class RoleMenusRelRepository extends Repository
{
    public function __construct(RoleMenusRel $model)
    {
        parent::__construct($model);
    }

    /**
     * 通过角色id获取菜单id
     *
     * @param int $roleId 角色id
     *
     * @return array
     */
    public function getMenuIdsByRoleId($roleId)
    {
        return $this->getQuery()->where('role_id', $roleId)->pluck('menu_id')->toArray();
    }

    /**
     * 删除角色菜单
     *
     * @param int   $roleId  角色id
     * @param array $menuIds 菜单id
     *
     * @return mixed
     */
    public function delRoleMenuIds($roleId, $menuIds)
    {
        return $this->getQuery()->where('role_id', '=', $roleId)->whereIn('menu_id', $menuIds)->delete();
    }

    /**
     * 通过指定字段删除，并返回已删除记录数
     *
     * @param string $field 字段名
     * @param mixed  $value 字段值
     *
     * @return void
     */
    public function deleteBy($field, $value)
    {
        return $this->getQuery()->where($field, $value)->delete();
    }

    /**
     * 根据用户获取拥有的菜单
     *
     * @param int $roleId 角色id
     *
     * @return array
     */
    public function getMenuByRole($roleId)
    {
        $query = $this->getQuery()->rightJoin('menu', 'role_menu_rel.menu_id', '=', 'menu.id');
        if ($roleId > 0) {
            $query->where('role_menu_rel.role_id', '=', $roleId);
        }

        return $query->where('menu.category', '=', Menu::CATEGORY_FUNC)->pluck('menu.id')->toArray();
    }

    /**
     * 根据菜单id获取记录
     *
     * @param int $menuId 菜单id
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function findByMenuId($menuId)
    {
        return $this->getQuery()->where('menu_id', $menuId)->first();
    }
}
