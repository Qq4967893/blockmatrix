<?php

namespace App\Repositories\Blockmatrix;

use Scaffold\Framework\Repositories\Repository;
use App\Models\Blockmatrix\Menu;

class MenuRepository extends Repository
{
    public function __construct(Menu $model)
    {
        parent::__construct($model);
    }

    /**
     * 通过id查询记录
     *
     * @param int $id id
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getById($id)
    {
        return $this->getQuery()->where('id', $id)->first();
    }

    /**
     * 通过菜单id获取菜单列表
     *
     * @param array  $roleMenu 菜单id(数组)
     * @param array  $column   查询字段
     * @param string $sort     排序规则
     *
     * @return array
     */
    public function getListById($roleMenu, $column, $sort = 'desc')
    {
        $query = Menu::query()->where('status', Menu::STATUS_NORMAL);
        if (! empty($roleMenu)) {
            $query->whereIn('id', $roleMenu);
        }

        return $query->select($column)->orderBy('pos', $sort)->get()->toArray();
    }

    /**
     * 获取指定菜单的下级菜单
     *
     * @param int $menuId 菜单id
     *
     * @return array
     */
    public function getByParentId($menuId)
    {
        return $this->getQuery()->where('parent_id', $menuId)->pluck('id')->toArray();
    }

    /**
     * 根据id修改菜单类型
     *
     * @param array $ids      id数组
     * @param int   $category 菜单类型
     *
     * @return void
     */
    public function updateCategoryByIds($ids, $category)
    {
        $this->getQuery()->whereIn('id', $ids)->update(['category' => $category]);
    }

    /**
     * 根据id修改状态
     *
     * @param int $id     主键id
     * @param int $status 状态(0:已删除,1:正常)
     *
     * @return void
     */
    public function updateStatusById($id, $status)
    {
        $this->getQuery()->where('id', $id)->update(['status' => $status]);
    }


    /**
     * 根据菜单id批量获取菜单
     *
     * @param array $ids     菜单id数组
     * @param array $where   条件
     * @param array $columns 字段
     *
     * @return array
     */
    public function getMenusByIds($ids, $where, $columns = ['*'])
    {
        $query = $this->getQuery();
        if (! empty($ids)) {
            $query->whereIn('id', $ids);
        }
        if (! empty($where)) {
            $query->where($where);
        }

        return $query->select($columns)->orderBy('pos', 'desc')->get()->toArray();
    }
}
