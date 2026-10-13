<?php

namespace App\Services\Blockmatrix;


use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\RoleAreaRepository;
use App\Repositories\Blockmatrix\RoleRepository;
use App\Repositories\Api\AreaRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Blockmatrix\UserLevel;
use App\Models\Blockmatrix\AdminUser;
use Illuminate\Support\Facades\Log;
use App\Utils\Util;
use Exception;

class AdminUserService
{

    public function __construct(
        private AdminUserRepository $repo,
        private RoleRepository      $roleRepo,
        private RoleAreaRepository  $roleAreaRepo,
        private AreaRepository      $areaRepo,
        private AuthService         $authService,
        private MenuService         $menuService,
        private UserLevelService    $userLevelService
    ) {
    }

    /**
     * 获取用户列表
     *
     * @param string $affiliation 用户归属
     * @param int    $roleId      角色id
     * @param int    $levelId     层级id
     * @param string $anyway      关键字查询
     * @param int    $status      状态
     * @param string $sort        排序字段
     * @param string $order       排序方式(desc:降序, asc:升序)
     * @param int    $offset      偏移量
     * @param int    $limit       每页条数
     *
     * @return array
     * @throws Exception
     */
    public function getList($affiliation, $roleId, $levelId, $anyway, $status, $sort, $order, $offset, $limit)
    {
        $leaderIds = $this->getLeaderIds($affiliation);
        [$manager, $count] = $this->repo->getList($leaderIds, $roleId, $levelId, $anyway, $status, $sort, $order, $offset, $limit);
        $this->buildData($manager);

        return ['data' => $manager, 'total_count' => $count];
    }

    /**
     * 根据主键id获取一条记录
     *
     * @param int $id ID
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getAdminUserById($id)
    {
        return $this->repo->first($id);
    }

    /**
     * 添加用户信息
     *
     * @param array $arr 用户信息
     *
     * @return void
     */
    public function add($arr)
    {
        $this->repo->insert($arr);
        Log::info(LOGIN_USER . "新增用户" . $arr['login_name']);
    }

    /**
     * 修改用户信息
     *
     * @param int   $id  用户id
     * @param array $arr 数据
     *
     * @return void
     */
    public function modify($id, $arr)
    {
        $this->repo->update($arr, $id);
    }

    /**
     * 重置密码
     *
     * @param int $id 用户id
     *
     * @return array
     */
    public function resetPassword($id)
    {
        $newPassword  = Util::generatePassword(8);
        $hashPassword = Hash::make($newPassword);
        $res          = $this->repo->update(['password' => $hashPassword], $id);

        if (! $res) {
            return ['is_succ' => false, 'message' => trans('public.message.upd_error')];
        }
        Log::info(LOGIN_USER . "重置了用户{$id}密码");

        return ['is_succ' => true, 'new_password' => $newPassword];
    }

    /**
     * 修改密码
     *
     * @param int    $id          用户ID
     * @param string $newPassword 新密码
     *
     * @return void
     */
    public function updatePassword($id, $newPassword)
    {
        $data['password'] = Hash::make($newPassword);
        $this->repo->update($data, $id);
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
        return $this->repo->getListByAffiliationIds($affiliationIds, $pluckColumn, $columns);
    }

    /**
     * 更新用户状态
     *
     * @param string $managerId 用户id(逗号分割)
     * @param int    $status    状态(0:删除; 1:正常)
     *
     * @return array
     */
    public function updateUserStatus($managerId, $status = AdminUser::STATUS_DELETE)
    {
        $uid   = explode(',', trim($managerId, ','));
        $idArr = array_filter($uid);
        if (empty($idArr)) {
            return ['is_succ' => false, 'message' => trans('public.message.wrong_threshold')];
        }
        $str = '';
        foreach ($idArr as $value) {
            $hasChild    = $this->getChildNumByManagerId($value);//是否有子级用户
            if ($hasChild != 0 ) {
                $str .= "子级用户";
                $str = trim($str, '、');
                $str .= $str ? ',' : "";
            }
        }
        if (! empty($str)) {
            return ['is_succ' => false, 'message' => trim($str, ',')];
        }
        $result = $this->repo->batchUpdateStatus($idArr, ['status' => $status]);
        if (! $result) {
            return ['is_succ' => false, 'message' => trans('public.message.upd_error')];
        }
        Log::info(LOGIN_USER . "修改用户" . $managerId . "状态为" . $status);

        return ['is_succ' => true, 'message' => trans('public.message.upd_success')];
    }

    /**
     * 根据用户层级id获取上级层级用户列表
     *
     * @param int $userLevelId 层级id
     *
     * @return array|\Illuminate\Database\Eloquent\Collection
     */
    public function getLeaderList($userLevelId)
    {
        if ($userLevelId == 0) {
            $managers = $this->repo->getBy('level_id', $userLevelId);
        } else {
            $parentUserLevels = $this->userLevelService->getParents($userLevelId);
            $managers         = $this->repo->getByLevelIds($parentUserLevels);
        }

        return $managers;
    }

    /**
     * 获取用户及其下级用户id
     *
     * @param int $adminUserId 用户id
     *
     * @return array
     */
    public function getIdsByParent($adminUserId)
    {
        $adminUserIds = [$adminUserId];
        $childrenIds  = $this->getDescendants($adminUserId);

        return array_merge($adminUserIds, $childrenIds);
    }

    /**
     * 获取用户树, 包含用户过滤的直属,无归属,全部用户等信息
     *
     * @param bool  $isFilter 是否是用于用户条件过滤,如果是则需要加上直属,无归属,全部用户等数据项
     *
     * @return array
     */
    public function getFilterTree($isFilter = true)
    {
        $manager     = Auth::user();
        $columns     = ['id', 'login_name', 'leader_id'];
        $allManagers = $this->repo->getAllUser($columns);

        return $this->getManagerTree($manager, $allManagers, $isFilter);
    }

    /**
     * 获取下级用户id
     *
     * @param int $userAffiliation 客户归属
     *
     * @return array
     * @throws Exception
     */
    public function getLeaderIds($userAffiliation)
    {
        $leaderIds = [];
        if (empty($userAffiliation)) {
            $user   = Auth::user();
            $userId = $user['id'];
            //管理员角色,全局数据权限和指定的角色可以查询所有客户
            $isRoot = $this->roleRepo->isRoot($user['role_id'], true);
            if (! $isRoot) {
                $descendantUserIds = $this->getDescendants($userId);
                $leaderIds[]       = $userId;
                $leaderIds         = array_merge($leaderIds, $descendantUserIds);
            }
        } else {
            [$userId, $affiliationType] = array_merge(explode('_', $userAffiliation), ['']);
            if (! is_numeric($userId)) {
                throw new Exception('用户归属参数有误!');
            }

            $affiliation = $this->repo->first($userId);
            if (empty($affiliation)) {
                throw new Exception("用户信息({$userId})不存在!");
            }

            if ($affiliationType === '1' || $affiliationType === '') {    //所有下属
                $descendantUserIds = $this->getDescendants($userId);
                $leaderIds[]       = $userId;
                $leaderIds         = array_merge($leaderIds, $descendantUserIds);
            } elseif ($affiliationType === '2') {  //直属
                $leaderIds[] = $userId;
            } else {
                throw new Exception('用户归属参数有误!');
            }
        }

        return $leaderIds;
    }

    /**
     * 递归获取一个用户所有子孙用户Id列表
     *
     * @param int|array $id        用户id
     * @param array     $mergedIds 用户id数组
     *
     * @return array
     */
    public function getDescendants($id, $mergedIds = [])
    {
        if (! is_array($id)) {
            $where[] = ['id', '!=', $id];
            $id      = [$id];
        }

        $where[] = ['status', '=', AdminUser::STATUS_NORMAL];
        $ids     = $this->repo->getIdByLeaderIds($id, $where);
        if (! empty($ids)) {
            $mergedIds = array_merge($mergedIds, $ids);

            return self::getDescendants($ids, $mergedIds);
        } else {
            return $mergedIds;
        }
    }

    /**
     * 查询数据是否存在
     *
     * @param string $column 字段名
     * @param string $value  字段值
     *
     * @return bool
     */
    public function userExists($column, $value)
    {
        return $this->repo->isExists($column, $value);
    }

    /**
     * 组合用户权限数据
     *
     * @param object $loginUser 登录用户对象
     *
     * @return object
     */
    public function buildLoginUser($loginUser)
    {
        $menusArr  = $this->authService->getMenusByRole($loginUser);
        $menuTree  = $this->menuService->getTree($menusArr);
        $menuInfos = [];
        foreach ($menusArr as $item) {
            $menuInfos[] = ['id' => $item['id'], 'name' => $item['name'], 'menu_key' => $item['menu_key']];
        }
        $loginUser['menus']     = $menuInfos;
        $loginUser['menu_tree'] = $menuTree;
        $loginUser['role_area'] = $loginUser['role_id']
            ? $this->roleAreaRepo->getAreaIdsByRoleId($loginUser['role_id'])
            : [];

        return $loginUser;
    }

    /**
     * 根据用户ID查询所属客户经理
     *
     * @param array $leaderIds 用户ID
     *
     * @return array
     */
    public function getAccountsManageByManagerIds($leaderIds = [])
    {
        $columns  = [
            'admin_user.id',
            'admin_user.login_name',
            'admin_user.leader_id',
            'admin_user.level_id',
            'user_level.type'
        ];
        $leader   = $this->repo->getUserLevelInfo($leaderIds, $columns);
        $allUsers = $this->repo->getUserLevelInfo([], $columns);
        $allUsers = array_column($allUsers, null, 'id');
        $list     = [];
        foreach ($leader as $value) {
            $list[$value['id']] = $this->getAccountsManageByLevelId($value['leader_id'], $allUsers) ?? $value['login_name'];
        }

        return $list;
    }

    /**
     * 根据id获取用户
     *
     * @param array $userIds 用户id
     *
     * @return array
     */
    public function getByIds($userIds)
    {
        return $this->repo->getColumnByIds($userIds, 'login_name');
    }

    /**
     * 获取用户信息
     *
     * @param int   $id      用户id
     * @param array $columns 字段
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getAdminUser($id, $columns = ['*'])
    {
        return $this->repo->first($id, $columns);
    }

    /**
     * 批量获取用户
     *
     * @param array $userIds 用户id
     * @param array $columns 字段
     *
     * @return array
     */
    public function getBatchBlockmatrixUsers($userIds, $columns = ['*'])
    {
        return $this->repo->getBatchUsers($userIds, $columns);
    }

    /**
     * 根据客户等级获取所属客户经理姓名
     *
     * @param int   $leaderId 用户ID
     * @param array $allUsers 所有用户
     *
     * @return string|null
     */
    private function getAccountsManageByLevelId($leaderId, $allUsers)
    {
        if ($leaderId == 0) {
            return null;
        }

        $leaderUser = $allUsers[$leaderId] ?? 0;
        if ($leaderUser['type'] == UserLevel::TYPE_INSIDE || empty($leaderUser['leader_id'])) {
            return $leaderUser['login_name'];
        } else {
            return $this->getAccountsManageByLevelId($leaderUser['leader_id'], $allUsers);
        }
    }

    /**
     * 查询用户的子级用户数量
     *
     * @param array|int $managerId 用户ID
     *
     * @return int
     */
    private function getChildNumByManagerId($managerId = null)
    {
        if ($managerId) {
            $managerId = is_array($managerId) ? $managerId : [intval($managerId)];
            return $this->repo->getChildNumber($managerId, ['status' => AdminUser::STATUS_NORMAL]);
        } else {
            return 0;
        }
    }

    /**
     * 获取当前登录用户的用户树
     *
     * @param object       $manager     当前登录用户
     * @param object|array $allManagers 全部用户
     * @param bool         $isFilter    是否是用于用户条件过滤,如果是则需要加上直属,无归属,全部用户等数据项
     *
     * @return array
     */
    private function getManagerTree($manager, $allManagers, $isFilter = false)
    {
        $tree   = [];
        $isRoot = $this->roleRepo->isRoot($manager['role_id']);
        if ($isFilter) {
            $tree[] = ["key" => "All", "value" => ""];
            if ($isRoot) {
                $tree[] = ["key" => "No RM", "value" => "none"];
            }
        }

        $topLevels = [];
        if ($isRoot) {
            $topLevels = $this->repo->getAllUser(['*'], false, true);
        } else {
            $topLevels[] = $manager;
        }

        $leaves = $this->buildTopLevels($manager, $topLevels, $allManagers, $isFilter);
        return array_merge($tree, $leaves);
    }

    /**
     * 构建用户树一级叶子节点
     *
     * @param object $manager     当前登录用户
     * @param array  $topLevels   树的顶级节点用户
     * @param array  $allManagers 全部用户
     * @param bool   $isFilter    是否是用于用户条件过滤,如果是则需要加上直属,无归属,全部用户等数据项
     *
     * @return array
     */
    private function buildTopLevels($manager, $topLevels, $allManagers, $isFilter = false)
    {
        $childrenLimitSize = $isFilter == true ? 2 : 1;
        $tree              = [];
        if ($isFilter) {
            $tree[] = ["key" => "Direct", "value" => $manager["id"] . "_2"];
        }
        foreach ($topLevels as $topLeaf) {
            $leafArr = $this->buildLeaf($topLeaf, $allManagers, $isFilter);

            $arr = ["key" => $topLeaf["login_name"], "value" => $topLeaf["id"]];
            if ($leafArr && sizeof($leafArr) >= $childrenLimitSize) {
                $arr["children"] = $leafArr;
            }
            $tree[] = $arr;
        }
        return $tree;
    }

    /**
     * 组装用户树
     *
     * @param array $leaf        节点用户
     * @param array $allManagers 全部用户
     * @param bool  $isFilter    是否用户条件过滤
     *
     * @return array
     */
    private function buildLeaf($leaf, $allManagers, $isFilter = false)
    {
        $childrenLimitSize = $isFilter == true ? 2 : 1;
        $tree              = [];
        if ($isFilter) {
            $tree[] = ["key" => "Direct", "value" => $leaf["id"] . "_2"];
        }

        foreach ($allManagers as $key => $item) {
            if ($item['leader_id'] === $leaf['id']) {
                unset($allManagers[$key]);
                $leafArr = $this->buildLeaf($item, $allManagers, $isFilter);
                $arr     = ["key" => $item["login_name"], "value" => $item["id"]];
                if ($leafArr && sizeof($leafArr) >= $childrenLimitSize) {
                    $arr["children"] = $leafArr;
                }
                $tree[] = $arr;
            }
        }
        return $tree;
    }

    /**
     * 组合用户列表数据
     *
     * @param array $managerArr 用户数据
     *
     * @return void
     */
    private function buildData(&$managerArr)
    {
        $leaderIds  = array_unique(array_column($managerArr, 'leader_id'));
        $levelIds   = array_unique(array_column($managerArr, 'level_id'));
        $roleIds    = array_unique(array_column($managerArr, 'role_id'));
        $managerIds = array_unique(array_column($managerArr, 'id'));

        $leaderInfos    = $this->repo->getColumnByIds($leaderIds, 'login_name');
        $userLevelInfos = $this->userLevelService->getColumnByIds($levelIds, 'name');
        $roleInfos      = $this->roleRepo->getColumnByIds($roleIds, 'name');

        //根据用户ID查询所属客户经理
        $accountManagers = $this->getAccountsManageByManagerIds($managerIds);

        foreach ($managerArr as &$manager) {
            $leaderId          = $manager['leader_id'];
            $levelId           = $manager['level_id'];
            $roleId            = $manager['role_id'];
            $manager['leader'] = $leaderInfos[$leaderId] ?? '';
            $manager['level']  = $userLevelInfos[$levelId] ?? '';
            if (isset($roleInfos[$roleId])) {
                $manager['roles']     = $roleInfos[$roleId];
                $areaIds              = $this->roleAreaRepo->getAreaIdsByRoleId($roleId);
                $areaNames            = $this->areaRepo->getBatchColumns($areaIds, ['id', 'name'], 'name');
                $manager['area_name'] = implode(',', $areaNames);
            } else {
                $manager['roles']     = '';
                $manager['area_name'] = '';
            }
            $manager['account_manager'] = $accountManagers[$manager['id']] ?? trans('manager.no_manager');
            $manager['status_name'] = trans('public.status.'.$manager['status']);
        }
    }
}
