<?php

namespace App\Services\Blockmatrix;

use App\Exceptions\BusinessException;
use App\Models\Blockmatrix\Menu;
use App\Repositories\Blockmatrix\UserMenuFavoriteRepository;
use Illuminate\Support\Facades\Log;

class UserMenuFavoriteService
{
    /** 单个用户最大收藏数量 */
    private const MAX_FAVORITE_COUNT = 5;

    public function __construct(
        private UserMenuFavoriteRepository $repo,
        private AuthService                $authService
    ) {
    }

    /**
     * 获取登录用户的收藏菜单列表
     *
     * 只返回当前用户有权限且状态正常的收藏菜单；
     * 权限被收走时自动隐藏，权限恢复后自动恢复展示
     *
     * @param int  $adminUserId 后台用户id
     * @param mixed $loginUser  登录者信息
     *
     * @return array
     */
    public function list($adminUserId, $loginUser)
    {
        $favoriteKeys = $this->repo->getMenuKeysByAdminUserId($adminUserId);
        if (empty($favoriteKeys)) {
            return [];
        }

        $authMenuByKey = [];
        foreach ($this->authService->getMenusByRole($loginUser) as $menu) {
            if ($menu['status'] == Menu::STATUS_NORMAL) {
                $authMenuByKey[$menu['menu_key']] = $menu;
            }
        }

        $favorites = [];
        foreach ($favoriteKeys as $menuKey) {
            if (isset($authMenuByKey[$menuKey])) {
                $menu = $authMenuByKey[$menuKey];
                $favorites[] = [
                    'id'        => $menu['id'],
                    'name'      => $menu['name'],
                    'parent_id' => $menu['parent_id'],
                    'pos'       => $menu['pos'],
                    'menu_key'  => $menu['menu_key'],
                ];
            }
        }

        return $favorites;
    }

    /**
     * 收藏菜单，重复收藏幂等
     *
     * @param int    $adminUserId 后台用户id
     * @param string $menuKey     菜单标识
     * @param mixed  $loginUser   登录者信息
     *
     * @return void
     * @throws BusinessException 菜单不存在、无权限或超出收藏上限
     */
    public function add($adminUserId, $menuKey, $loginUser)
    {
        if (empty($this->findAuthorizedMenu($menuKey, $loginUser))) {
            throw new BusinessException(trans('public.message.non_existent'));
        }

        if (!empty($this->repo->getByAdminUserIdAndMenuKey($adminUserId, $menuKey))) {
            return;
        }

        if ($this->repo->countByAdminUserId($adminUserId) >= self::MAX_FAVORITE_COUNT) {
            throw new BusinessException(
                trans('public.message.favorite_limit_exceeded', ['limit' => self::MAX_FAVORITE_COUNT])
            );
        }

        $this->repo->create([
            'admin_user_id' => $adminUserId,
            'menu_key'      => $menuKey,
        ]);
        Log::info('admin user favorite menu', ['admin_user_id' => $adminUserId, 'menu_key' => $menuKey]);
    }

    /**
     * 取消收藏菜单，记录不存在时幂等成功
     *
     * @param int    $adminUserId 后台用户id
     * @param string $menuKey     菜单标识
     *
     * @return void
     */
    public function remove($adminUserId, $menuKey)
    {
        $this->repo->deleteByAdminUserIdAndMenuKey($adminUserId, $menuKey);
        Log::info('admin user remove menu favorite', ['admin_user_id' => $adminUserId, 'menu_key' => $menuKey]);
    }

    /**
     * 在当前用户可见范围内查找状态正常的菜单
     *
     * @param string $menuKey   菜单标识
     * @param mixed  $loginUser 登录者信息
     *
     * @return array
     */
    private function findAuthorizedMenu($menuKey, $loginUser)
    {
        foreach ($this->authService->getMenusByRole($loginUser) as $menu) {
            if ($menu['menu_key'] === $menuKey && $menu['status'] == Menu::STATUS_NORMAL) {
                return $menu;
            }
        }

        return [];
    }
}
