<?php

namespace App\Http\Controllers\Blockmatrix;

use App\Http\Controllers\BaseController;
use App\Http\Requests\AdminUser\AdminUserAddRequest;
use App\Http\Requests\AdminUser\AdminUserUpdateRequest;
use App\Http\Requests\AdminUser\ChangePasswordRequest;
use App\Http\Requests\AdminUser\ModifyEmailPhoneRequest;
use App\Services\Blockmatrix\AdminUserService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\Blockmatrix\AdminUser;
use App\Models\Blockmatrix\UserLevel;
use Illuminate\Http\Request;
use App\Utils\Util;
use Symfony\Component\HttpFoundation\JsonResponse;

class AdminUserController extends BaseController
{
    public function __construct(
        private AdminUserService $adminUserService
    ) {
    }

    /**
     * 用户列表
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function list(Request $request)
    {
        /* 用户归属 */
        $affiliation = $request->input('user_affiliation', '');
        /* 角色id */
        $roleId = $request->input('role_id', null);
        /* 层级id */
        $levelId = $request->input('level_id', null);
        /* 关键字查询 */
        $anyway = $request->input('anyway', '');
        /* 状态 */
        $status = $request->input('status', AdminUser::STATUS_NORMAL);
        /* 排序字段 */
        $sort = $request->input('sort', 'created_at');
        /* 排序方式 */
        $order = $request->input('order', 'desc');
        /* 分页大小 */
        $limit = $request->input('limit', 20);
        /* 分页偏移量 */
        $offset = $request->input('offset', 0);

        $data = $this->adminUserService->getList($affiliation, $roleId, $levelId, $anyway, $status, $sort, $order, $offset, $limit);

        return $this->succeed($data);
    }

    /**
     * 获取用户权限数据
     *
     * @return JsonResponse
     */
    public function getPermissions()
    {
        $loginUser = $this->adminUserService->buildLoginUser(Auth::user());

        return $this->succeed($loginUser);
    }

    /**
     * 添加用户
     *
     * @param AdminUserAddRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(AdminUserAddRequest $request)
    {
        $loginName = $request->input('login_name', '');
        $password = $request->input('password');
        $email    = $request->input('email');
        $phone    = $request->input('phone');
        $city     = $request->input('city');
        $address  = $request->input('address');
        $description = $request->input('description');
        $roleId   = $request->input('role_id');
        $levelId  = $this->normalizeOptionalLevelId($request->input('level_id'));
        $leaderId = $request->input('leader_id', null);


        if (in_array($levelId, UserLevel::NO_SALES_TEAM)) {
            return $this->fail(trans('manager.sale.not_empty'));
        }

        $arr['login_name'] = $loginName;
        $arr['password']  = Hash::make($password);
        $arr['email']     = $email;
        $arr['phone']     = $phone;
        $arr['city']      = $city;
        $arr['address']   = $address;
        $arr['description']      = $description;
        $arr['role_id']   = $roleId;
        $arr['level_id']  = $levelId;
        $arr['leader_id'] = $leaderId ?? null;
        try {
            $this->adminUserService->add($arr);
        } catch (QueryException $e) {
            if ($this->isDuplicateLoginNameException($e)) {
                return $this->fail(trans('manager.user.name_exists'));
            }

            throw $e;
        }

        return $this->succeed([], trans('public.message.add_success'));
    }

    /**
     * 修改用户
     *
     * @param AdminUserUpdateRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function modify(AdminUserUpdateRequest $request)
    {
        $managerId = $request->input('manager_id', 0);
        $loginName  = $request->input('login_name', '');
        $email     = $request->input('email');
        $phone     = $request->input('phone');
        $city      = $request->input('city');
        $address   = $request->input('address');
        $description = $request->input('description');
        $roleId    = $request->input('role_id');
        $levelId   = $this->normalizeOptionalLevelId($request->input('level_id'));
        $leaderId  = $request->input('leader_id', null);

        if ($request->has('level_id') && in_array($levelId, UserLevel::NO_SALES_TEAM) && empty($salesTeam)) {
            return $this->fail(trans('manager.sale.not_empty'));
        }

        if ($this->isSysAdminById($managerId)) {
            return $this->fail(trans('manager.message.user_not_exist'));
        }

        $isExists = $this->adminUserService->userExists('id', $managerId);
        if ($isExists == false) {
            return $this->fail(trans('manager.message.user_not_exist'));
        }

        $arr['login_name']  = $loginName;
        $arr['email']     = $email;
        $arr['phone']     = $phone;
        $arr['city']      = $city;
        $arr['address']   = $address;
        $arr['description']  = $description;
        $arr['role_id']   = $roleId;
        $arr['leader_id'] = $leaderId;
        if ($request->has('level_id')) {
            $arr['level_id'] = $levelId;
        }

        try {
            $this->adminUserService->modify($managerId, $arr);
        } catch (QueryException $e) {
            if ($this->isDuplicateLoginNameException($e)) {
                return $this->fail(trans('manager.user.name_exists'));
            }

            throw $e;
        }

        return $this->succeed([], trans('public.message.upd_success'));
    }

    private function isDuplicateLoginNameException(QueryException $e): bool
    {
        $message = $e->getMessage();

        return $e->getCode() === '23000'
            && (str_contains($message, 'uniq_login_name') || str_contains($message, 'admin_user.login_name'));
    }

    /**
     * 判断指定ID是否为 sysAdmin 账号
     */
    private function isSysAdminById(int $id): bool
    {
        $user = $this->adminUserService->getAdminUserById($id);

        return $user && ($user['login_name'] ?? '') === AdminUser::SYS_ADMIN;
    }

    /**
     * 判断逗号分隔的ID列表中是否包含 sysAdmin
     */
    private function isSysAdminInIds(string $ids): bool
    {
        $idArr = array_filter(explode(',', trim($ids, ',')));
        foreach ($idArr as $id) {
            if ($this->isSysAdminById((int) $id)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeOptionalLevelId($levelId): int
    {
        if ($levelId === null || $levelId === '') {
            return 0;
        }

        return (int) $levelId;
    }

    /**
     * 重置用户密码
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        /* 用户id */
        $id = $request->input('manager_id');
        if (empty($id) && !is_numeric($id)) {
            return $this->fail(trans('user.message.not_exist'));
        }
        if ($this->isSysAdminById((int) $id)) {
            return $this->fail(trans('manager.message.user_not_exist'));
        }
        $res = $this->adminUserService->resetPassword($id);
        if (!$res['is_succ']) {
            return $this->fail($res['message']);
        }

        return $this->succeed($res['new_password']);
    }

    /**
     * 用户修改密码
     *
     * @param ChangePasswordRequest $request request
     *
     * @return JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $oldPassword = $request->input("old_passwd");
        $newPassword = $request->input("new_passwd");

        $loginId = Auth::user()['id'];
        $manager = $this->adminUserService->getAdminUserById($loginId);
        if (is_null($manager)) {
            return $this->fail(trans('manager.message.user_not_exist'));
        }
        if (!Hash::check($oldPassword, $manager['password'])) {
            return $this->fail(trans('manager.user.original_password_err'));
        }
        $this->adminUserService->updatePassword($loginId, $newPassword);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->succeed([], trans('public.message.upd_success'));
    }

    /**
     * 删除/恢复用户
     *
     * @param string $managerId 客户数据(逗号分割)
     * @param int    $status    状态(0:删除,1:恢复)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeStatus($managerId, $status)
    {
        if ($this->isSysAdminInIds($managerId)) {
            return $this->fail(trans('manager.message.user_not_exist'));
        }
        $result = $this->adminUserService->updateUserStatus($managerId, $status);
        if ($result['is_succ'] == false) {
            return $this->fail($result['message']);
        }

        return $this->succeed([], $result['message']);
    }

    /**
     * 生成随机密码
     *
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function generatePassword()
    {
        $password = Util::generatePassword(8);

        return $this->succeed(['key' => $password]);
    }

    /**
     * 根据用户层级id获取上级层级用户列表
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLeaderList(Request $request)
    {
        $userLevelId = $request->input('user_level_id', null); //用户层级id
        $data        = $this->adminUserService->getLeaderList($userLevelId);

        return $this->succeed($data);
    }

    /**
     * 获取用户树, 包含用户过滤的直属,无归属,全部用户等信息
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFilterTree()
    {
        $data = $this->adminUserService->getFilterTree();

        return $this->succeed($data);
    }

    /**
     * 获取普通用户树
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTree()
    {
        $data = $this->adminUserService->getFilterTree(false);

        return $this->succeed($data);
    }

    /**
     * 获取手机号邮箱
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getEmailPhone()
    {
        $user      = Auth::user();
        $id        = $user['id'];
        $adminUser = $this->adminUserService->getAdminUser($id, ['email', 'phone']);

        return $this->succeed(['email' => $adminUser['email'] ?? '-', 'phone' => $adminUser['phone'] ?? '-']);
    }

    /**
     * 修改手机号邮箱
     *
     * @param ModifyEmailPhoneRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function modifyEmailPhone(ModifyEmailPhoneRequest $request)
    {
        $email = $request->input('email', '');
        $phone = $request->input('phone', '');

        $user = Auth::user();
        $id   = $user['id'];

        $insert = [];
        if (!empty($email)) {
            $insert['email'] = $email;
        }
        if (!empty($phone)) {
            $insert['phone'] = $phone;
        }
        $this->adminUserService->modify($id, $insert);

        return $this->succeed();
    }
}
