<?php

namespace App\Repositories\Api;

use App\Models\Api\UserSocial;
use Scaffold\Framework\Repositories\Repository;

class UserSocialRepository extends Repository
{
    public function __construct(
        UserSocial $model
    ) {
        parent::__construct($model);
    }

    /**
     * 获取用户的社交绑定
     *
     * @param int   $userCode UserCode
     *
     * @return array
     */
    public function getByUserCode($userCode)
    {
        return $this->getQuery()->where('user_code', $userCode)->where('status', UserSocial::$status['bound'])
            ->select('provider', 'provider_user_id')->get()->toArray();
    }
}
