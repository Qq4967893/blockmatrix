<?php

namespace App\Repositories\Blockmatrix;

use App\Models\Blockmatrix\UserMenuFavorite;
use Scaffold\Framework\Repositories\Repository;

class UserMenuFavoriteRepository extends Repository
{
    public function __construct(UserMenuFavorite $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取用户收藏的菜单标识，按收藏时间倒序
     *
     * @param int $adminUserId 后台用户id
     *
     * @return array
     */
    public function getMenuKeysByAdminUserId($adminUserId)
    {
        return $this->getQuery()
            ->where('admin_user_id', $adminUserId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('menu_key')
            ->toArray();
    }

    /**
     * 获取用户的单条收藏记录
     *
     * @param int    $adminUserId 后台用户id
     * @param string $menuKey     菜单标识
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getByAdminUserIdAndMenuKey($adminUserId, $menuKey)
    {
        return $this->getQuery()
            ->where('admin_user_id', $adminUserId)
            ->where('menu_key', $menuKey)
            ->first();
    }

    /**
     * 统计用户收藏数量
     *
     * @param int $adminUserId 后台用户id
     *
     * @return int
     */
    public function countByAdminUserId($adminUserId)
    {
        return $this->getQuery()->where('admin_user_id', $adminUserId)->count();
    }

    /**
     * 删除用户的单条收藏，记录不存在时不做任何处理
     *
     * @param int    $adminUserId 后台用户id
     * @param string $menuKey     菜单标识
     *
     * @return int 已删除记录数
     */
    public function deleteByAdminUserIdAndMenuKey($adminUserId, $menuKey)
    {
        return $this->getQuery()
            ->where('admin_user_id', $adminUserId)
            ->where('menu_key', $menuKey)
            ->delete();
    }
}
