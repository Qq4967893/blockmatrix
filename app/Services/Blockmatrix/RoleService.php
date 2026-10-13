<?php

namespace App\Services\Blockmatrix;

use App\Repositories\Blockmatrix\RoleRepository;
use App\Repositories\Blockmatrix\RoleMenusRelRepository;
use App\Repositories\Blockmatrix\RoleAreaRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Api\AreaRepository;
use App\Repositories\Blockmatrix\MenuRepository;
use App\Models\Blockmatrix\Menu;
use App\Models\Blockmatrix\Role;
use Illuminate\Support\Facades\Log;

class RoleService
{

    public function __construct(
        private RoleRepository         $repo,
        private RoleMenusRelRepository $roleMenusRelRepo,
        private RoleAreaRepository     $roleAreaRepo,
        private AdminUserRepository    $adminUserRepo,
        private AreaRepository         $areaRepo,
        private MenuRepository         $menuRepo
    ) {
    }

    /**
     * 获取角色列表
     *
     * @param int    $parentId 上级id
     * @param string $name     角色名称
     * @param int    $status   状态
     * @param int    $areaId   大区id
     * @param int    $limit    每页条数
     * @param int    $offset   偏移量
     *
     * @return array
     */
    public function getList($parentId, $name, $status, $areaId, $limit, $offset)
    {
        [$roles, $count] = $this->repo->getList($parentId, $name, $status, $areaId, $limit, $offset);
        $this->buildData($roles);

        return [$roles, $count];
    }

    /**
     * 通过id查询记录
     *
     * @param int    $roleId 角色id
     * @param string $column 字段
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|null
     */
    public function getById($roleId, $column = '')
    {
        return $this->repo->getById($roleId, $column);
    }

    /**
     * 添加角色
     *
     * @param string $name       角色名称
     * @param int    $parentId   父类id
     * @param string $menuIdsStr 菜单(逗号分割)
     * @param string $areaIds    大区(逗号分割)
     *
     * @return void
     */
    public function add($name, $parentId, $menuIdsStr, $areaIds)
    {
        $menuIds          = explode(',', $menuIdsStr);
        $arr['status']    = Role::STATUS_NORMAL;
        $arr['name']      = $name;
        $arr['parent_id'] = $parentId;

        $roleId = $this->repo->save($arr);
        $this->saveByRoleId($roleId, $menuIds);

        //保存角色大区
        $areaArr = explode(',', $areaIds);
        if (! empty($areaArr)) {
            $roleArea = [];
            foreach ($areaArr as $areaId) {
                $area            = [];
                $area['role_id'] = $roleId;
                $area['area_id'] = $areaId;
                $roleArea[]      = $area;
            }
            if (! empty($roleArea)) {
                $this->roleAreaRepo->insert($roleArea);
            }
        }
        Log::info(LOGIN_USER . '添加了角色(' . json_encode($arr) . ') 菜单(' . $menuIdsStr . ')');
        Log::info(LOGIN_USER . "新增角色信息" . $name);
    }

    /**
     * 修改角色
     *
     * @param int    $roleId     角色id
     * @param string $name       角色名称
     * @param int    $parentId   父类id
     * @param string $menuIdsStr 菜单(,分割)
     * @param string $areaIds    大区(,分割)
     *
     * @return void
     */
    public function modify($roleId, $name, $parentId, $menuIdsStr, $areaIds)
    {
        $menuIds = explode(',', $menuIdsStr);
        $this->saveByRoleId($roleId, $menuIds);
        $arr['name']      = $name;
        $arr['parent_id'] = $parentId;
        $this->repo->update($arr, $roleId, 'id');

        //保存角色大区
        $areaArr = explode(',', $areaIds);
        if (! empty($areaArr)) {
            $roleArea = [];
            foreach ($areaArr as $areaId) {
                $area            = [];
                $area['role_id'] = $roleId;
                $area['area_id'] = $areaId;
                $roleArea[]      = $area;
            }
            if (! empty($roleArea)) {
                $this->roleAreaRepo->delete($roleId, 'role_id');
                $this->roleAreaRepo->batchInsert($roleArea);
            }
        }
        Log::info(LOGIN_USER . '修改了角色(' . json_encode($arr) . ') 菜单(' . $menuIdsStr . ')');
        Log::info(LOGIN_USER . "修改角色信息" . $name);
    }

    /**
     * 保存"角色菜单关联"信息
     *
     * @param int   $roleId  角色id
     * @param array $menuIds 菜单id
     */
    public function saveByRoleId($roleId, $menuIds)
    {
        $old  = $this->roleMenusRelRepo->getMenuIdsByRoleId($roleId);
        $diff = array_diff($old, $menuIds);
        if ($diff) {
            $this->updateChildMenu($roleId, $diff);
        }
        $data = [];
        foreach ($menuIds as $menuId) {
            $data[] = ["role_id" => $roleId, "menu_id" => $menuId];
        }
        $this->roleMenusRelRepo->deleteBy('role_id', $roleId);
        $this->roleMenusRelRepo->insert($data);
    }

    /**
     * 删除角色,并递归删除下级所有角色
     *
     * @param int $roleId 角色id
     *
     * @return array
     */
    public function remove($roleId)
    {
        $role = $this->repo->getById($roleId);
        if (empty($role)) {
            return ['is_succ' => false, 'message' => trans('message.not_found')];
        }
        $role = $role->toArray();
        //判断当前当前角色下是否有登录账号
        $count = $this->adminUserRepo->getCountByRoleId($roleId);
        if ($count) {
            return ['is_succ' => false, 'message' => trans('manager.role.unable_delete')];
        }
        [$rolesList,] = $this->repo->getList(null, null, Role::STATUS_NORMAL);
        $childRoles   = $this->getChildren($role, $rolesList);
        $childRoles[] = $role;
        $removeIds    = [];
        foreach ($childRoles as $item) {
            $this->roleAreaRepo->delete($item['id'], 'role_id');
            $this->roleMenusRelRepo->delete($item['id'], 'role_id');
            $removeIds[] = $item['id'];
        }
        $this->repo->updateStatusByIds($removeIds, Role::STATUS_DELETE);
        $this->roleAreaRepo->delete($roleId, 'role_id');
        $strIds = implode(',', $removeIds);
        Log::info(LOGIN_USER . "删除角色" . $strIds);

        return ['is_succ' => true];
    }

    /**
     * 获取大区角色
     *
     * @param int $roleId 角色id
     *
     * @return array
     */
    public function getAreaRoles(int $roleId)
    {
        $isRoot = $this->repo->isRoot($roleId);
        $data   = [];
        if ($isRoot) {
            $roleId = null;
        }
        $roleAreas = $this->roleAreaRepo->getAreaByRoleId($roleId);

        //获取所有角色名称
        $roleNames = $this->repo->getAllRoleNames();
        //获取角色对应大区数量
        $roleAreaNum = $this->roleAreaRepo->getRoleAreaNum();
        $areaNames   = $this->areaRepo->getAllAreaNames();

        $multiple = 'MultipleAreas';

        foreach ($roleAreas as $roleArea) {
            $areaId = $roleArea['area_id'];
            $roleId = $roleArea['role_id'];

            $areaName = $areaNames[$areaId] ?? 'All';
            $roleName = $roleNames[$roleId] ?? '';
            if (empty($roleName)) {
                continue;
            }

            $roleInfo = ['role_id' => $roleId, 'role_name' => $roleName];

            $num = $roleAreaNum[$roleId] ?? 0;
            $key = $areaId . '_area'; //为了保证前端获取的是json对象
            if ($num < 2) {
                $data[$key]['area_name']   = $areaName;
                $data[$key]['area_role'][] = $roleInfo;
            } else {
                $data['multiple']['area_name'] = $multiple;
                $areaRole[$roleId]             = $roleInfo;
                $data['multiple']['area_role'] = array_values($areaRole);
            }
        }
        ksort($data);

        return $data;
    }

    /**
     * 删除所有子角色的权限
     *
     * @param int   $roleId 角色ID
     * @param array $menu   菜单ID数组
     */
    private function updateChildMenu($roleId, $menu)
    {
        $child = $this->repo->getByParentId($roleId);
        if ($child) {
            foreach ($child as $value) {
                $this->roleMenusRelRepo->delRoleMenuIds($value, $menu);
                $this->updateChildMenu($value, $menu);
            }
        }
    }

    /**
     * 根据父级角色获取递归子级角色的数组,不包含传入的父级角色
     *
     * @param object $parentRole 父级角色
     * @param array  $allRoles   全部角色
     *
     * @return array 递归子级角色数组
     */
    private function getChildren($parentRole, $allRoles)
    {
        $items = [];
        foreach ($allRoles as $role) {
            if ($role['parent_id'] == $parentRole['id']) {
                $childItems = $this->getChildren($role, $allRoles);
                $items      = array_merge($items, [$role], $childItems);
            }
        }
        return $items;
    }

    /**
     * 构建角色返回数据
     *
     * @param array $rolesList 角色数组
     *
     * @return void
     */
    private function buildData(&$rolesList)
    {
        $parentRolesIds   = array_unique(array_column($rolesList, 'parent_id'));
        $parentRolesInfos = $this->repo->getColumnByIds($parentRolesIds, 'name');
        $areaNum          = $this->roleAreaRepo->getRoleAreaNum();
        $areaName         = 'name';
        foreach ($rolesList as &$role) {
            $menuIds             = $this->roleMenusRelRepo->getMenuIdsByRoleId($role['id']);
            $menusArr            = $this->menuRepo->getMenusByIds($menuIds, ['category' => Menu::CATEGORY_FUNC], ['id', 'name']);
            $role['menus']       = $menusArr;
            $role['parent_name'] = $parentRolesInfos[$role['parent_id']] ?? '';

            $isRoot = $this->repo->isRoot($role['id']);
            if (! isset($areaNum[$role['id']]) || $areaNum[$role['id']] < 1) {
                $role['area_name'] = ($isRoot ? 'All Areas' : 'Unselected Area');
                $role['area_id']   = null;
            } elseif ($areaNum[$role['id']] == 1) {
                $areaId            = $this->roleAreaRepo->getAreaIdsByRoleId($role['id']);
                $role['area_id']   = $areaId[0];
                $area = $this->areaRepo->getById($areaId[0]);
                $role['area_name'] = $area ? $area->name : '';
            } else {
                $areaIds           = $this->roleAreaRepo->getAreaIdsByRoleId($role['id']);
                $role['area_id']   = $areaIds;
                $areaNames         = $this->areaRepo->getBatchColumns($areaIds, ['id', 'name'], 'name');
                $role['area_name'] = implode(',', $areaNames);
            }
        }
    }
}
