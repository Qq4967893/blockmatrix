<?php

namespace App\Services\Blockmatrix;

use App\Repositories\Blockmatrix\MenuRepository;
use App\Repositories\Blockmatrix\MenuEndpointRepository;
use App\Repositories\Blockmatrix\RoleMenusRelRepository;
use App\Models\Blockmatrix\AdminUser;
use App\Models\Blockmatrix\Menu;

class AuthService
{    public function __construct(
        private RoleMenusRelRepository $roleMenusRelRepo,
        private MenuRepository         $menuRepo,
        private MenuEndpointRepository $menuEndpointRepo
    ) {
    }

    /**
     * 获取账户可以访问的地址
     *
     * @param object $loginUser 登录用户
     *
     * @return array
     */
    public function getUserMenu($loginUser)
    {
        $menusArr = $this->getMenusByRole($loginUser);
        $menuInfos = [];
        foreach ($menusArr as $item) {
            $menuInfos[] = $item['id'];
        }
        $apiPaths = $this->menuEndpointRepo->getApiPathsByMenuIds($menuInfos);

        return array_unique($apiPaths);
    }

    /**
     * 获取登录用户菜单
     *
     * @param object $loginUser 登录者信息
     *
     * @return array
     */
    public function getMenusByRole($loginUser)
    {
        $where = [];
        if ($loginUser['login_name'] == AdminUser::SYS_ADMIN) {
            $where[] = ['category', '=', Menu::CATEGORY_SYS];
            $menuIds = [];
        } else {
            $menuIds = $this->roleMenusRelRepo->getMenuIdsByRoleId($loginUser['role_id']);
            $where[] = ['category', '=', Menu::CATEGORY_FUNC];
        }
        return $this->menuRepo->getMenusByIds($menuIds, $where);
    }

    /**
     * 修改请求链接
     *
     * @param string $path 请求地址
     *
     * @return string
     */
    public function modMethod($path)
    {
        $pathArr = explode('/', $path);
        $path    = '';
        foreach ($pathArr as $item) {
            $path .= !empty($item) && !is_numeric($item) && stripos($item, ',') === false ? $item . '/' : '';
        }
        return substr($path, 0, -1);
    }
}
