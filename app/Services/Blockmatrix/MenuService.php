<?php

namespace App\Services\Blockmatrix;

use App\Repositories\Blockmatrix\MenuRepository;
use App\Repositories\Blockmatrix\RoleMenusRelRepository;
use App\Repositories\Blockmatrix\MenuEndpointRepository;
use App\Models\Blockmatrix\Menu;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Throwable;

class MenuService
{
    public function __construct(
        private MenuRepository         $repo,
        private RoleMenusRelRepository $roleMenusRelRepo,
        private MenuEndpointRepository $menuEndpointRepo,
        private MenuEndpointService    $menuEndpointService
    ) {
    }

    /**
     * 获取角色菜单
     *
     * @param int $roleId 角色id
     *
     * @return array
     */
    public function getList($roleId)
    {
        $roleMenu = [];
        if ($roleId >= 0) {
            $roleMenu = $this->roleMenusRelRepo->getMenuByRole($roleId);
        }
        $menus = $this->repo->getListById($roleMenu, ['*']);
        $menus = $this->buildData($menus);

        return ['data' => $menus, 'auth_data' => []];
    }

    /**
     * 根据id查询菜单详情
     *
     * @param int $id 菜单id
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function info($id)
    {
        return $this->repo->getById($id);
    }

    /**
     * 新增菜单
     *
     * @param string $name     菜单名称
     * @param int    $parentId 上级菜单
     * @param int    $category 菜单类型
     * @param int    $pos      菜单序号
     * @param string $menuKey  菜单标识
     * @param string $apiPath  API路径
     *
     * @return void
     * @throws \Throwable
     */
    public function add($name, $parentId, $category, $pos, $menuKey, $apiPath = '')
    {
        $maxPos           = $this->repo->max('pos', Menu::STATUS_NORMAL, 'status');
        $arr['name']      = $name;
        $arr['parent_id'] = $parentId;
        $arr['pos']       = intval($pos) > 0 ? $pos : $maxPos + 1;
        $arr['category']  = $category;
        $arr['menu_key']  = $menuKey;

        DB::connection(DB_BM)->transaction(function () use ($arr, $parentId, $apiPath) {
            $id = $this->repo->insertGetId($arr);
            if ($id && $parentId) {
                try {
                    $isSucc = $this->saveMenuEndpoints($id, $apiPath);
                } catch (Throwable $e) {
                    throw new Exception('新增菜单信息发生异常: ' . $e->getMessage(), $e->getCode(), $e);
                }
                if (!$isSucc) {
                    throw new Exception('新增菜单信息发生异常: 保存菜单接口地址失败');
                }
            }
        });
        Log::info(LOGIN_USER . "新增菜单" . $name);
    }

    /**
     * 修改菜单
     *
     * @param int    $menuId  菜单id
     * @param string $name    菜单名称
     * @param int    $parentId 上级菜单
     * @param int    $category 菜单类型
     * @param int    $pos      菜单序号
     * @param string $apiPath  API路径
     *
     * @return void
     * @throws \Throwable
     */
    public function modify($menuId, $name, $parentId, $category, $pos, $apiPath)
    {
        $arr['parent_id'] = $parentId;
        $arr['name']      = $name;
        $arr['category']  = $category;
        $arr['pos']       = $pos;

        DB::connection(DB_BM)->transaction(function () use ($menuId, $parentId, $category, $apiPath, $arr) {
            $this->repo->update($arr, $menuId, 'id');
            $this->saveMenuEndpoints($menuId, $parentId ? $apiPath : '', false);
            $childMenu = $this->getChildMenus($menuId, []);
            if (!empty($childMenu)) {
                $this->repo->updateCategoryByIds($childMenu, $category);
            }
        });
        Log::info(LOGIN_USER . "修改菜单" . $name);
    }

    /**
     * 组合菜单数据
     *
     * @param array $menuList 菜单数组
     *
     * @return array
     */
    private function buildData($menuList)
    {
        $topMenus = [];
        foreach ($menuList as $menu) {
            if ($menu['parent_id'] == null) {
                $topMenus[] = $menu;
            }
        }
        foreach ($topMenus as &$menu) {
            $this->buildHierarchyData($menu, $menuList);
        }

        return $topMenus;
    }

    /**
     * 递归生成下级菜单
     *
     * @param array $parentMenu 父级菜单
     * @param array $allMenus   所有菜单列表
     */
    private function buildHierarchyData(&$parentMenu, $allMenus)
    {
        $items = [];
        foreach ($allMenus as $menu) {
            if ($menu['parent_id'] == $parentMenu['id']) {
                $this->buildHierarchyData($menu, $allMenus);
                $menu['parent_name'] = $parentMenu['name'];
                if ($menu['parent_id']) {
                    $menuEndpoint = $this->menuEndpointService->getApiPath($menu['id']);
                    $menu         = array_merge($menu, $menuEndpoint);
                }
                $items[] = $menu;
            }
        }
        $parentMenu['items'] = $items;
    }

    /**
     * 保存菜单对应API路径
     *
     * @param int    $id      菜单id
     * @param string $apiPath API路径
     * @param bool   $create  是否新建 true-新建 false-修改
     *
     * @return bool
     */
    private function saveMenuEndpoints($id, $apiPath, $create = true)
    {
        if (!$id) {
            throw new Exception('菜单ID不存在');
        }
        $apiPaths = $this->menuEndpointService->parseApiPaths($apiPath);
        if ($create === false) {
            $this->menuEndpointRepo->delete($id, 'menu_id');
        }
        if (empty($apiPaths)) {
            return true;
        }

        $data = [];
        foreach ($apiPaths as $key => $path) {
            $data[$key]['menu_id']    = $id;
            $data[$key]['api_path']   = $path;
            $data[$key]['created_at'] = date('Y-m-d H:i:s');
        }

        try {
            $res = $this->menuEndpointRepo->insert($data);
        } catch (Throwable $e) {
            throw new Exception('保存菜单接口地址失败: ' . $e->getMessage(), $e->getCode(), $e);
        }

        if ($res) {
            return true;
        }

        throw new Exception('保存菜单接口地址失败');
    }

    /**
     * 获取菜单下的所有子菜单
     *
     * @param int   $menuId 菜单id
     * @param array $data   菜单数组
     *
     * @return array
     */
    private function getChildMenus($menuId, $data)
    {

        $menu = $this->repo->getByParentId($menuId);
        $data = array_merge($data, $menu);
        if (!empty($menu)) {
            foreach ($menu as $item) {
                return $this->getChildMenus($item, $data);
            }
        }

        return $data;
    }

    /**
     * 根据id修改状态
     *
     * @param int $id     主键id
     * @param int $status 状态(0:已删除,1:正常)
     *
     * @return void
     */
    public function updateStatus($id, $status)
    {
        $this->repo->updateStatusById($id, $status);
    }

    /**
     * 获取菜单层级树
     *
     * @param array $allMenus 全部菜单
     *
     * @return array
     */
    public function getTree($allMenus)
    {
        $topLeaves = array_filter(
            $allMenus,
            function ($menu) {
                return $menu['parent_id'] == null;
            }
        );

        return $this->buildTopLevels($topLeaves, $allMenus);
    }

    /**
     * 构建树一级叶子节点
     *
     * @param array $topLevels 树的顶级节点
     * @param array $allMenus  全部菜单
     *
     * @return array 用户树
     */
    private function buildTopLevels($topLevels, $allMenus)
    {
        $tree = [];
        foreach ($topLevels as $topLeaf) {
            $leafArr = $this->buildLeaf($topLeaf, $allMenus);

            $arr = ['key' => $topLeaf['name'], 'value' => $topLeaf['id'], 'menu_key' => $topLeaf['menu_key']];
            if ($leafArr && sizeof($leafArr) > 0) {
                $arr['children'] = $leafArr;
            }
            $tree[] = $arr;
        }

        return $tree;
    }

    /**
     * 构建树
     *
     * @param array $leaf     树
     * @param array $allMenus 全部菜单
     *
     * @return array
     */
    private function buildLeaf($leaf, $allMenus)
    {
        $tree = [];
        foreach ($allMenus as $key => $item) {
            if ($item['parent_id'] === $leaf['id']) {
                unset($allMenus[$key]);
                $leafArr = $this->buildLeaf($item, $allMenus);
                $arr     = ['key' => $item['name'], 'value' => $item['id'], 'menu_key' => $item['menu_key']];
                if ($leafArr && sizeof($leafArr) > 0) {
                    $arr['children'] = $leafArr;
                }
                $tree[] = $arr;
            }
        }

        return $tree;
    }

}
