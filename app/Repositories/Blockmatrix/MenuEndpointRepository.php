<?php

namespace App\Repositories\Blockmatrix;

use App\Models\Blockmatrix\MenuEndpoint;
use Scaffold\Framework\Repositories\Repository;

class MenuEndpointRepository extends Repository
{
    public function __construct(MenuEndpoint $model)
    {
        parent::__construct($model);
    }

    /**
     * 根据菜单id获取接口配置
     *
     * @param  int  $menuId  菜单id
     * @return array
     */
    public function getByMenuId($menuId)
    {
        return $this->getQuery()->where('menu_id', $menuId)->get()->toArray();
    }

    /**
     * 根据菜单id批量获取API路径
     *
     * @param  array  $menuIds  菜单id数组
     * @return array
     */
    public function getApiPathsByMenuIds($menuIds)
    {
        return $this->getQuery()->whereIn('menu_id', $menuIds)->pluck('api_path')->toArray();
    }
}
