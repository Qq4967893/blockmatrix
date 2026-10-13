<?php

namespace App\Repositories\Blockmatrix;

use Scaffold\Framework\Repositories\Repository;
use Illuminate\Database\Eloquent\Model;
use App\Models\Blockmatrix\AdminUser;
use App\Utils\Phone;
use App\Utils\Email;

class AdminUserRepository extends Repository
{
    public function __construct(AdminUser $model)
    {
        parent::__construct($model);
    }

    /**
     * 用户列表
     *
     * @param array  $leaderIds 客户归属
     * @param int    $roleId    角色id
     * @param int    $levelId   层级id
     * @param string $anyway    关键字
     * @param int    $status    状态
     * @param string $sort      排序字段
     * @param string $order     排序方式
     * @param int    $offset    偏移量
     * @param int    $limit     每页条数
     *
     * @return array
     */
    public function getList($leaderIds, $roleId, $levelId, $anyway, $status, $sort, $order, $offset, $limit)
    {
        $query = $this->getQuery();
        if (!empty($leaderIds)) {
            if (count($leaderIds) == 1) {
                $query->where('leader_id', $leaderIds[0]);
            } else {
                $query->whereIn('leader_id', $leaderIds);
            }
        }
        if (!empty($roleId)) {
            $query->where('role_id', $roleId);
        }
        if (!empty($levelId)) {
            $query->where('level_id', $levelId);
        }
        if (in_array($status, [AdminUser::STATUS_DELETE, AdminUser::STATUS_NORMAL])) {
            $query->where('status', $status);
        }
        if ($anyway != '') {
            $query->where($this->genAnywayCond($anyway));
        }
        $sort = $sort == 'in_time' ? 'id' : $sort;
        $query->where('login_name', '!=', AdminUser::SYS_ADMIN);
        $count = $query->count();
        $data  = $query->orderBy($sort, $order)->offset($offset)->limit($limit)->get()->toArray();

        return [$data, $count];
    }


    /**
     * 获取账号信息
     *
     * @param string $loginName 登录账号
     *
     * @return \Illuminate\Database\Eloquent\Builder|Model|object|null
     */
    public function getUserInfoByAccount($loginName, $column = 'login_name')
    {
        return $this->getQuery()->where('status', AdminUser::STATUS_NORMAL)
            ->where($column, $loginName)->first();
    }

    /**
     * 获取当前角色下登录账号个数
     *
     * @param int $roleId 角色id
     *
     * @return int
     */
    public function getCountByRoleId($roleId)
    {
        return $this->getQuery()->where('status', AdminUser::STATUS_NORMAL)->where('role_id', $roleId)->count();
    }

    /**
     * 根据id查询下级用户id
     *
     * @param array $ids   id数组
     * @param array $where 查询附加条件
     *
     * @return array
     */
    public function getIdByLeaderIds($ids, $where)
    {
        return $this->getQuery()->whereIn('leader_id', $ids)->where($where)->pluck('id')->toArray();
    }

    /**
     * 根据id批量获取数据
     *
     * @param array  $ids    id数组
     * @param string $column 字段
     *
     * @return array
     */
    public function getColumnByIds($ids, $column)
    {
        return $this->getQuery()->whereIn('id', $ids)->pluck($column, 'id')->toArray();
    }

    public function searchIdsByLoginName(string $keyword): array
    {
        if ($keyword === '') {
            return [];
        }

        return $this->getQuery()
            ->where('status', AdminUser::STATUS_NORMAL)
            ->where('login_name', '!=', AdminUser::SYS_ADMIN)
            ->where('login_name', 'like', '%' . addcslashes($keyword, '%_') . '%')
            ->pluck('id')
            ->toArray();
    }

    /**
     * 通过id获取用户姓名
     *
     * @param int $id 用户id
     *
     * @return mixed
     */
    public function getUserNameById($id)
    {
        return $this->getQuery()->where('id', $id)->value('login_name');
    }

    /**
     * 批量更新用户状态
     *
     * @param array $ids id数组
     * @param array $arr 更新数据
     *
     * @return int
     */
    public function batchUpdateStatus($ids, $arr)
    {
        return $this->getQuery()->whereIn('id', $ids)->update($arr);
    }

    /**
     * 获取用户层级信息
     *
     * @param array $ids     用户id
     * @param array $columns 查询字段
     *
     * @return array
     */
    public function getUserLevelInfo($ids, $columns = ['*'])
    {
        $query = $this->getQuery()->select($columns)
            ->leftJoin('user_level', 'admin_user.level_id', '=', 'user_level.id')
            ->where('admin_user.status', AdminUser::STATUS_NORMAL);
        if (!empty($ids)) {
            $query->whereIn('admin_user.id', $ids);
        }

        return $query->get()->toArray();
    }

    /**
     * 查询数据是否存在
     *
     * @param string $column 字段名
     * @param string $value  字段值
     *
     * @return bool
     */
    public function isExists($column, $value)
    {
        return $this->getQuery()->where($column, $value)->exists();
    }

    /**
     * 查询下级用户数量
     *
     * @param array $managerId 用户id
     * @param array $where     条件
     *
     * @return int
     */
    public function getChildNumber($managerId, $where)
    {
        return $this->getQuery()->whereIn('leader_id', $managerId)->where($where)->count();
    }

    /**
     * 通过层级ID批量获取用户列表
     *
     * @param array $levelIds 层级ID
     *
     * @return array
     */
    public function getByLevelIds($levelIds)
    {
        if (empty($levelIds)) {
            return [];
        }

        return $this->getQuery()
            ->whereIn('level_id', $levelIds)
            ->where('status', AdminUser::STATUS_NORMAL)
            ->where('login_name', '!=', AdminUser::SYS_ADMIN)
            ->get()->toArray();

    }

    /**
     * 获取用户表所有的正常使用用户
     *
     * @param array $columns     查询字段
     * @param bool  $levelIdType 是否判断参数level_id (false:不判断, true:判断)
     * @param bool  $topLeader   是否获取leader_id为0的数据(false:不获取, true:获取)
     *
     * @return array
     */
    public function getAllUser($columns = ['*'], $levelIdType = false, $topLeader = false)
    {
        $query = $this->getQuery()->where('status', AdminUser::STATUS_NORMAL)->where('login_name', '!=', AdminUser::SYS_ADMIN);
        if ($levelIdType === true) {
            $query->where('level_id', '<>', 0);
        }
        if ($topLeader) {
            $query->where('leader_id', null);
        }

        return $query->get($columns)->toArray();
    }

    /**
     * 根据归属Id查询用户列表
     *
     * @param array       $affiliationIds 归属Id列表
     * @param string|null $pluckColumn    pluck_column
     * @param array       $columns        要查询的字段列表
     *
     * @return array
     */
    public function getListByAffiliationIds($affiliationIds, $pluckColumn = null, $columns = ['*'])
    {
        $query = $this->getQuery()->select($columns)->whereIn('id', $affiliationIds);
        if (!is_null($pluckColumn)) {
            return $query->pluck($pluckColumn, 'id')->toArray();
        }

        return $query->get()->toArray();
    }

    /**
     * 查询角色下所有用户
     *
     * @param int   $roleId  角色id
     * @param array $columns 字段
     *
     * @return array
     */
    public function getUserByRoleId($roleId, $columns = ['*'])
    {
        return $this->getQuery()
            ->where('role_id', $roleId)
            ->where('status', AdminUser::STATUS_NORMAL)
            ->select($columns)
            ->get()
            ->toArray();
    }

    /**
     * 获取用户角色大区
     *
     * @param array $where 查询的条件
     *
     * @return array
     */
    public function userRoleArea($where)
    {
        return $this->getQuery()->leftJoin('role_area', 'admin_user.role_id', '=', 'role_area.role_id')
            ->select('role_area.area_id')
            ->where($where)
            ->pluck('role_area.area_id')
            ->toArray();
    }

    /**
     * 获取多个用户
     *
     * @param array $userIds 用户id
     * @param array $columns 字段名称
     *
     * @return array
     */
    public function getBatchUsers($userIds, $columns)
    {
        return $this->getQuery()->select($columns)->whereIn('id', $userIds)->get()->toArray();
    }

    /**
     * 组合关键字搜索条件
     *
     * @param $anyway
     *
     * @return array|\Closure
     */
    private function genAnywayCond($anyway)
    {
        if (Phone::is($anyway)) {
            $condition = function ($query) use ($anyway) {
                $query->where('phone', $anyway);
            };
        } elseif (Email::is($anyway)) {
            $condition = function ($query) use ($anyway) {
                $query->where('email', $anyway);
            };
        } else {
            $condition = function ($query) use ($anyway) {
                $query->where('login_name', 'like', "%$anyway%");
            };
        }

        return $condition;
    }
}
