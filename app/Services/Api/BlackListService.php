<?php

namespace App\Services\Api;

use App\Models\Api\UserProhibition;
use App\Models\Api\User;
use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\BlackListRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Api\UserProhibitionRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use Exception;

class BlackListService
{
    public function __construct(
        private BlackListRepository        $blackListRepo,
        private UserRepository             $userRepo,
        private UserOperationLogRepository $userOperationLogRepo,
        private UserProhibitionRepository  $userProhibitionRepo,
        private AdminUserRepository        $adminUserRepo
    ) {
    }

    /**
     * 获取黑名单列表
     *
     * @param int    $userCode 用户ID
     * @param string $email 邮箱
     * @param string $createdStartTime 添加开始时间
     * @param string $createdEndTime 添加结束时间
     * @param string $regStartTime 注册开始时间
     * @param string $regEndTime 注册结束时间
     * @param string $sort
     * @param string $order
     * @param int    $limit
     * @param int    $offset
     *
     * @return array
     * @throws Exception
     */
    public function getList($userCode, $email, $createdStartTime, $createdEndTime, $regStartTime, $regEndTime, $sort, $order, $limit, $offset)
    {
        [$blackLists, $count] = $this->blackListRepo->getList($userCode, $email, $regStartTime, $regEndTime);
        if ($count == 0) {
            return [$blackLists, $count];
        }

        $userCodes = array_column($blackLists, 'user_code');
        // user code是否已入金
        $hasBeenCashInflow = $this->userRepo->hasBeenCashInflow($userCodes);

        // 限制内容
        $userPrivileges = $this->userProhibitionRepo->getAll($userCodes);
        $userCodePrivileges = collect($userPrivileges)->groupBy('user_code')->map(function ($group) {
            return $group->pluck('type')
            ->values()
            ->toArray();
        })->toArray();

        $userIds = array_column($blackLists, 'id');
        // 获取最后操作人
        $lastOperateUserIds = $this->userOperationLogRepo->getBatchOperateInfo(UserOperationLog::TARGET_TYPE_BLACK, $userIds, 0);
        $adminUserIds = array_column($lastOperateUserIds, 'operator_id');
        $adminUsers = $this->adminUserRepo->getColumnByIds($adminUserIds, 'login_name');

        // 获取添加时间，添加原因
        $userOperateRecords = $this->userOperationLogRepo->getBatchOperateInfo(UserOperationLog::TARGET_TYPE_BLACK, $userIds);
        foreach ($blackLists as &$blackList) {
            $userId = $blackList['id'];
            $blackListUserCode = $blackList['user_code'];
            $adminUserId = $lastOperateUserIds[$userId]['operator_id'] ?? 0;
            $blackList['operate_user_name'] = (int) $adminUserId === 0
                ? trans('fund_flow.actor.system')
                : ($adminUsers[$adminUserId] ?? '');
            $blackList['created_at'] = $userOperateRecords[$userId]['created_at'];
            $blackList['reason'] = json_decode($userOperateRecords[$userId]['content'], true)['reason'];

            // 获取用户限制内容
            $blackListLimits = [];
            foreach (($userCodePrivileges[$blackListUserCode] ?? []) as $userCodePrivilege) {
                $blackListLimits[] = ['type'=> $userCodePrivilege, "name" => UserProhibition::types()[$userCodePrivilege]];
            }
            $blackList['limits'] = $blackListLimits;
            $blackList['limits_text'] = implode(', ', array_column($blackListLimits, 'name'));

            if (is_null($blackList['email'])) {
                $blackList['email'] = '-';
            }

            $blackList['reg_method'] = User::resolveRegistrationWayLabel($blackList);
            $blackList['reg_method_text'] = $blackList['reg_method'];

            if (in_array($blackListUserCode, $hasBeenCashInflow)){
                $blackList['has_been_cash_inflow'] = 1;
            } else {
                $blackList['has_been_cash_inflow'] = 0;
            }
            $blackList['has_been_cash_inflow_text'] = trans('user.is_pay.' . $blackList['has_been_cash_inflow']);
        }

        $collectQuery = collect($blackLists);
        if ($createdStartTime && $createdEndTime) {
            // 创建时间范围内搜索
            $collectQuery = $collectQuery->whereBetween("created_at", [$createdStartTime, $createdEndTime]);
        }
        $count = $collectQuery->count();
        // 分页排序
        if ($order == 'desc') {
            $collectQuery = $collectQuery->sortByDesc($sort);
        } else {
            $collectQuery = $collectQuery->sortBy($sort);
        }
        $pagedData = $collectQuery->slice($offset, $limit);
        return [$pagedData->values()->toArray(), $count];
    }

    /**
     * 添加黑名单
     *
     * @param int    $userCode 用户ID
     * @param string $reason 原因
     * @param int    $downLine 是否强制下线
     * @param array  $privileges 权限
     *
     * @return array
     * @throws Exception
     */
    public function add($userCode, $reason, $downLine, $privileges)
    {
        $userInfo = $this->userRepo->firstBy('user_code', $userCode);
        if ($userInfo['type'] == User::TYPE_TEST) {
            return ['is_succ' => false, 'message' => 'test user code Cannot be blacklist'];
        }
        if ($userInfo['type'] == User::TYPE_BLACK) {
            return ['is_succ' => false, 'message' => 'User is already on the blacklist'];
        }

        $this->userRepo->update(['type' => User::TYPE_BLACK], $userCode, 'user_code');

        $this->userOperationLogRepo->addRecord($userInfo['id'], UserOperationLog::TARGET_TYPE_BLACK, json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE), UserOperationLog::ACTION_TYPE_ADD, $userCode);

        // 添加黑名单用户ID限制
        $insertUserPrivileges = [];
        foreach ($privileges as $type){
            $insertUserPrivileges[] = ['user_code' => $userCode, 'type' => $type];
        }
        $this->userProhibitionRepo->insert($insertUserPrivileges);

        if ($downLine) {
            // 强制用户下线
            $this->userRepo->downLine($userCode);
        }

        return ['is_succ' => true, 'message' => trans('public.message.add_success')];
    }

    /**
     * 删除黑名单
     *
     * @param int $userCode 用户ID
     *
     * @throws Exception
     */
    public function delete($userCode){
        // 更新用户为真实账户（黑名单不可转换为测试账户）
        $this->userRepo->update(['type' => User::TYPE_REAL], $userCode, 'user_code');

        // 删除用户权限设置
        $this->userProhibitionRepo->delete($userCode, 'user_code');

        $userInfo = $this->userRepo->firstBy('user_code', $userCode);
        $this->userOperationLogRepo->addRecord($userInfo['id'], UserOperationLog::TARGET_TYPE_BLACK, UserOperationLog::DEFAULT_CONTENT, UserOperationLog::ACTION_TYPE_DELETE, $userCode);
    }

    /**
     * 获取黑名单权限
     *
     * @return array
     * @throws Exception
     */
    public function getPrivileges()
    {
        $result = [];
        foreach (UserProhibition::types() as $key => $value) {
            $result[] = ['type' => $key, 'name' => $value];
        }
        return $result;
    }

    /**
     * 修改黑名单权限
     *
     * @param int   $userCode 用户ID
     * @param array $privilegeTypes //权限类型：1限制登录，2限制入金，3限制出金，4限制开仓
     *
     * @throws Exception
     */
    public function updatePrivilege($userCode, $privilegeTypes){
        $this->userProhibitionRepo->delete($userCode, 'user_code');

        $data = [];
        foreach ($privilegeTypes as $type){
            $data[] = ['user_code' => $userCode, 'type' => $type];
        }
        $this->userProhibitionRepo->insert($data);

        $userInfo = $this->userRepo->firstBy('user_code', $userCode);
        $this->userOperationLogRepo->addRecord($userInfo['id'], UserOperationLog::TARGET_TYPE_BLACK, json_encode($data, JSON_UNESCAPED_UNICODE), UserOperationLog::ACTION_TYPE_UPDATE, $userCode);
        return ['is_succ' => true, 'message' => trans('public.message.upd_success')];
    }

    /**
     * 是否存在出金限制
     *
     * @param int   $userCode 用户ID
     *
     * @return bool
     * @throws Exception
     */
    public function hasWithdrawalRestriction($userCode){
        return $this->userProhibitionRepo->isExists($userCode, UserProhibition::TYPE_WITHDRAW);
    }
}
