<?php

namespace App\Services\Blockmatrix;

use App\Repositories\Blockmatrix\MenuEndpointRepository;

class MenuEndpointService
{
    public function __construct(
        private MenuEndpointRepository $repo
    ) {}

    /**
     * 获取菜单对应的API路径
     *
     * @param  int  $menuId  菜单id
     * @return array
     */
    public function getApiPath($menuId)
    {
        $menuEndpoints = $this->repo->getByMenuId($menuId);

        return ['api_path' => implode(',', array_column($menuEndpoints, 'api_path'))];
    }

    /**
     * 将逗号分隔的API路径转换为去重后的路径数组
     *
     * @param  string  $apiPath  API路径
     * @return array
     */
    public function parseApiPaths($apiPath)
    {
        $apiPaths = array_map('trim', explode(',', (string) $apiPath));
        $apiPaths = array_filter($apiPaths, static fn ($path) => $path !== '');

        return array_values(array_unique($apiPaths));
    }
}
