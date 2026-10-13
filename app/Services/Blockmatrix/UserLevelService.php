<?php

namespace App\Services\Blockmatrix;

use App\Repositories\Blockmatrix\UserLevelRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Models\Blockmatrix\UserLevel;

class UserLevelService
{
    public function __construct(
        private UserLevelRepository $repo,
        private AdminUserRepository $adminUserRepo
    ) {
    }

    /**
     * 获取所有用户层级
     *
     * @return array
     */
    public function getList()
    {
        $data = $this->orderData($this->repo->getList());
        return $this->buildData($data);
    }

    /**
     * 新增数据
     *
     * @param array $arr 添加数据
     *
     * @return void
     */
    public function add($arr)
    {
        $this->repo->insert($arr);
    }

    /**
     * 修改层级
     *
     * @param int   $id  主键id
     * @param array $arr 更新数据
     *
     * @return bool
     */
    public function updateLevel($id, $arr)
    {
        return $this->repo->update($arr, $id, 'id');
    }

    /**
     * 删除层级
     *
     * @param int $id id
     *
     * @return void
     */
    public function delete($id)
    {
        $this->repo->delete($id, 'id');
    }

    /**
     * 根据id查询一条记录
     *
     * @param int $id id
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getById($id)
    {
        return $this->repo->firstBy('id', $id);
    }

    /**
     * 通过id批量获取数据
     *
     * @param array  $ids    id数组
     * @param string $column 字段
     *
     * @return array
     */
    public function getColumnByIds($ids, $column)
    {
        return $this->repo->getColumnByIds($ids, $column);
    }

    /**
     * 查询层级的所有父级层级
     *
     * @param int  $id   记录id
     * @param bool $flag 是否外部层级
     *
     * @return array
     */
    public function getParents($id, $flag = true)
    {
        $data = $this->repo->firstBy('id', $id, ['id', 'parent_id', 'type']);
        if (empty($data)) {
            return [];
        }
        static $arr = [];
        if ($data['type'] == UserLevel::TYPE_OUTSIDE && $flag == true) {
            $res = $this->repo->getIdsByType(UserLevel::TYPE_INSIDE);
            $arr = array_merge($arr, $res);
        }
        if (isset($data->parent_id) && $data->parent_id != 0) {
            self::getParents($data->parent_id, false);
            $arr[] = $data->parent_id;
        }

        return $arr;
    }

    /**
     * 组装层级数据
     *
     * @param array $userLevelArr 层级数组
     */
    public function buildData(&$userLevelArr)
    {
        if (empty($userLevelArr)) {
            return [];
        }
        $levelIds = [];
        foreach ($userLevelArr as $item) {
            $levelIds[] = $item['id'];
        }
        $userArr   = $this->adminUserRepo->getByLevelIds($levelIds);
        $userInfos = [];
        foreach ($userArr as $user) {
            $userInfos[$user['level_id']][] = $user['login_name'];
        }

        foreach ($userLevelArr as &$ul) {
            $ul['users'] = $userInfos[$ul['id']] ?? [];
        }
        return $userLevelArr;
    }

    /**
     * 用户层级按照父子排序
     *
     * @param array $array 层级数据
     * @param int   $parentId   父ID
     *
     * @return array
     */
    private function orderData($array, $parentId = 0)
    {
        static $subs = [];
        foreach ($array as $v) {
            if ($v['parent_id'] == $parentId) {
                $v['pname'] = isset($v['pname']) && $v['pname'] ? $v['pname'] : "First level";
                $subs[]     = $v;
                $this->orderData($array, $v['id']);
            }
        }
        return $subs;
    }
}
