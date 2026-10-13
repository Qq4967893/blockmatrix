<?php

namespace App\Http\Controllers\Blockmatrix;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use App\Http\Requests\Role\RoleAddRequest;
use App\Http\Requests\Role\RoleUpdateRequest;
use App\Services\Blockmatrix\RoleService;
use App\Models\Blockmatrix\Role;
use Illuminate\Support\Facades\Auth;

class RoleController extends BaseController
{
    public function __construct(
        private RoleService $roleService
    ) {
    }

    /**
     * 角色列表
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        /** 上级id */
        $parentId = $request->input('parent_id');
        /** 角色名称 */
        $name     = $request->input('name');
        /** 状态 0:删除 1:正常 */
        $status   = $request->input('status', Role::STATUS_NORMAL);
        /** 大区ID */
        $areaId   = $request->input('area_id');
        /** 分页条数 */
        $limit    = $request->input('limit', 20);
        /** 分页偏移量 */
        $offset   = $request->input('offset', 0);

        [$data, $count] = $this->roleService->getList($parentId, $name, $status, $areaId, $limit, $offset);

        return $this->succeed(['data' => $data, 'total_count' => $count]);
    }

    /**
     * 角色详情
     *
     * @param int $roleId 角色id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function info($roleId)
    {
        $role = $this->roleService->getById($roleId);
        if (is_null($role) || $role['status'] == Role::STATUS_DELETE) {
            $this->fail(trans('public.message.non_existent'));
        }

        return $this->succeed(['data' => $role]);
    }

    /**
     * 添加角色
     *
     * @param RoleAddRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(RoleAddRequest $request)
    {
        $name       = $request->input('name');
        $parentId   = $request->input('parent_id', 0);
        $menuIdsStr = $request->input('menu_ids');
        $areaIds    = $request->input('area_ids');

        $this->roleService->add($name, $parentId, $menuIdsStr, $areaIds);

        return $this->succeed();
    }

    /**
     * 修改角色
     *
     * @param RoleUpdateRequest $request request
     * @param int               $roleId  角色id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function modify(RoleUpdateRequest $request, $roleId)
    {
        $name       = $request->input('name');               //角色名
        $parentId   = $request->input('parent_id', 0);       //上级id
        $menuIdsStr = $request->input('menu_ids');           //菜单id,例如'1,3,4...'
        $areaIds    = $request->input('area_ids');        //大区id例如 1,2,3...

        if ($parentId != 0) {
            $parentRole = $this->roleService->getById($parentId);
            if (empty($parentRole)) {
                return $this->fail(trans('manager.role.non_superiors'));
            }
        }
        $role = $this->roleService->getById($roleId);
        if (empty($role) || $role['status'] == Role::STATUS_DELETE) {
            return $this->fail(trans('public.message.non_existent'));
        }
        $this->roleService->modify($roleId, $name, $parentId, $menuIdsStr, $areaIds);

        return $this->succeed();
    }

    /**
     * 修改角色状态
     *
     * @param Request $request request
     * @param int     $roleId  角色id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $roleId)
    {
        /** 状态 0-删除 1-正常 */
        $status = $request->input('status');
        if (empty($roleId)) {
            return $this->fail(trans('public.message.not_be_empty'));
        }
        if (! isset($status) || ! in_array($status, [Role::STATUS_DELETE, Role::STATUS_NORMAL])) {
            return $this->fail(trans('public.message.state_error'));
        }

        if ($status == Role::STATUS_NORMAL) {
            return $this->fail(trans('public.message.wrong_threshold'));
        }

        $removeResult = $this->roleService->remove($roleId);
        if ($removeResult['is_succ'] == false) {
            return $this->fail($removeResult['message']);
        }

        return $this->succeed();
    }

    /**
     * 获取大区角色
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAreaRoles()
    {
        $user = Auth::user();
        $role = $this->roleService->getById($user['role_id']);
        if (empty($role)) {
            return $this->fail(trans('public.message.non_existent'));
        }
        $data = $this->roleService->getAreaRoles($role['id']);

        return $this->succeed(['data' => $data]);
    }
}
