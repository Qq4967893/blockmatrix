<?php

namespace App\Http\Controllers\Blockmatrix;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Menu\MenuAddRequest;
use App\Http\Requests\Menu\MenuUpdateRequest;
use App\Services\Blockmatrix\MenuService;
use App\Services\Blockmatrix\RoleMenusRelService;
use Illuminate\Http\Request;
use App\Models\Blockmatrix\Menu;
use Illuminate\Support\Facades\Log;

class MenuController extends BaseController
{
    public function __construct(
        private MenuService         $menuService,
        private RoleMenusRelService $roleMenusRelService
    ) {
    }

    /**
     * 获取角色菜单
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        /** 角色id */
        $roleId = $request->input('role_id', '-1');
        $data   = $this->menuService->getList($roleId);

        return $this->succeed($data);
    }

    /**
     * 菜单详情
     *
     * @param int $menuId 菜单id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function info($menuId)
    {
        $menu = $this->menuService->info($menuId);
        if (empty($menu) || $menu['status'] == Menu::STATUS_DELETE) {
            return $this->fail(trans('public.message.not_found'));
        }

        return $this->succeed($menu);
    }

    /**
     * 新增菜单
     *
     * @param MenuAddRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function add(MenuAddRequest $request)
    {
        $name     = $request->input('name');
        $parentId = $request->input('parent_id', null);
        $category = $request->input('category');
        $pos      = $request->input('pos', 0);
        $apiPath  = $request->input('api_path', '');
        $menuKey  = $request->input('menu_key', '');

        $this->menuService->add($name, $parentId, $category, $pos, $menuKey, $apiPath);

        return $this->succeed();
    }

    /**
     * 修改菜单
     *
     * @param MenuUpdateRequest $request request
     * @param int               $menuId  菜单id
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Throwable
     */
    public function modify(MenuUpdateRequest $request, $menuId)
    {
        $name     = $request->input('name');
        $parentId = $request->input('parent_id', null);
        $category = $request->input('category');
        /** 菜单位置 */
        $pos      = $request->input('pos', 0);
        $apiPath = $request->input('api_path', '');

        $menu = $this->menuService->info($menuId);
        if (empty($menu) || $menu['status'] == Menu::STATUS_DELETE) {
            return $this->fail(trans('public.message.non_existent'));
        }

        $this->menuService->modify($menuId, $name, $parentId, $category, $pos, $apiPath);

        return $this->succeed();
    }

    /**
     * 修改菜单状态
     *
     * @param Request $request request
     * @param int     $menuId  菜单id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request, $menuId)
    {
        /** 状态 0:删除菜单 1:正常使用菜单 */
        $status = $request->input('status');

        if (! isset($status) || ! in_array($status, [Menu::STATUS_DELETE, Menu::STATUS_NORMAL])) {
            return $this->fail(trans('public.message.state_error'));
        }
        $menu = $this->menuService->info($menuId);
        if (empty($menu)) {
            return $this->fail(trans('public.message.non_existent'));
        }

        if ($status == Menu::STATUS_DELETE) {
            $roleMenus = $this->roleMenusRelService->findByMenuId($menuId);
            if (! empty($roleMenus)) {
                return $this->fail(trans('public.message.correlation'));
            }
        }

        $this->menuService->updateStatus($menuId, $status);
        Log::info(LOGIN_USER . "更新菜单" . $menuId . "状态" . $status);

        return $this->succeed();
    }
}
