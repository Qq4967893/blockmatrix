<?php

namespace App\Services\Blockmatrix;

use App\Repositories\Blockmatrix\AdminUserRepository;

class LoginService
{

    public function __construct(
        private AdminUserRepository $repo)
    {
    }

    /**
     * 获取账号信息
     *
     * @param string $loginName 账号
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public function getUserInfoByAccount($loginName)
    {
        return $this->repo->getUserInfoByAccount($loginName);
    }

    /**
     * 获取用户角色所属大区id
     *
     * @param int $userId 用户id
     *
     * @return array
     */
    public function userRoleArea($userId)
    {
        return $this->repo->userRoleArea(['admin_user.id' => $userId]);
    }

}
