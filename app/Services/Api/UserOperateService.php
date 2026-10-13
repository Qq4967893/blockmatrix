<?php

namespace App\Services\Api;

use App\Repositories\Api\UserOperateRepository;

class UserOperateService
{
    public function __construct(
        private UserOperateRepository $userOperateRepo
    ) {
    }

    /**
     * 发送消息检查用户ID
     *
     * @param int   $areaId    大区id
     * @param array $user_codes 用户ID
     *
     * @return bool
     */
    public function sendMsgCheckUser($areaId, $user_codes)
    {
        foreach ($user_codes as $user_code) {
            // area_id was removed from users; just ensure the account exists
            $userInfo = $this->userOperateRepo->getUserByUserCode($user_code, ['id']);
            if (empty($userInfo)) {
                return false;
            }
        }

        return true;
    }
}
