<?php

namespace App\Services\Blockmatrix;

use App\Repositories\Blockmatrix\RoleMenusRelRepository;

class RoleMenusRelService
{
    public function __construct(
        private RoleMenusRelRepository $repo
    ) {
    }

    /**
     * 根据菜单id查询一条记录
     *
     * @param int $menuId 菜单id
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function findByMenuId($menuId)
    {
        return $this->repo->findByMenuId($menuId);
    }
}
