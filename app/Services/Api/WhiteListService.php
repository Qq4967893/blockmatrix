<?php

namespace App\Services\Api;

use App\Models\Blockmatrix\UserOperationLog;
use App\Models\Api\User;
use App\Repositories\Api\WhiteListRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class WhiteListService
{
    public function __construct(
        private WhiteListRepository        $whiteListRepo,
        private UserRepository             $userRepo,
        private UserOperationLogRepository $userOperationLogRepo,
        private AdminUserRepository        $adminUserRepo,
    ) {
    }

    /**
     * 用户白名单列表
     *
     * @param string $userCode            用户编码
     * @param string $registerTimeStart   注册开始时间
     * @param string $registerTimeEnd     注册结束时间
     * @param string $whiteAddTimeStart   加入白名单开始时间
     * @param string $whiteAddTimeEnd     加入白名单结束时间
     * @param int    $offset              偏移量
     * @param int    $limit               每页条数
     *
     * @return array
     * @throws Exception
     */
    public function getList(
        $userCode,
        $registerTimeStart,
        $registerTimeEnd,
        $whiteAddTimeStart,
        $whiteAddTimeEnd,
        $offset,
        $limit
    ) {
        $registerTimeStart = $this->normalizeDateTime($registerTimeStart);
        $registerTimeEnd   = $this->normalizeDateTime($registerTimeEnd);
        $whiteAddTimeStart = $this->normalizeDateTime($whiteAddTimeStart);
        $whiteAddTimeEnd   = $this->normalizeDateTime($whiteAddTimeEnd);

        $filterByWhiteTime = !empty($whiteAddTimeStart) || !empty($whiteAddTimeEnd);
        $whiteOperateTargetIds = [];
        if ($filterByWhiteTime) {
            $whiteOperateTargetIds = $this->userOperationLogRepo->getOperateTargetIdsByTimeRange(
                UserOperationLog::TARGET_TYPE_WHITE,
                $whiteAddTimeStart,
                $whiteAddTimeEnd
            );

            if (empty($whiteOperateTargetIds)) {
                return [[], 0];
            }
        }

        [$data, $count] = $this->whiteListRepo->getList(
            $userCode,
            $registerTimeStart,
            $registerTimeEnd,
            $whiteOperateTargetIds,
            $filterByWhiteTime,
            $offset,
            $limit
        );

        if (empty($data)) {
            return [$data, $count];
        }

        $ids = array_column($data, 'id');
        //白名单操作人
        $operators    = $this->userOperationLogRepo->getBatchOperateInfo(UserOperationLog::TARGET_TYPE_WHITE, $ids);
        $adminUserIds = array_column($operators, 'operator_id', 'target_id');
        $userNames    = $this->adminUserRepo->getColumnByIds($adminUserIds, 'login_name');
        $addTimes     = array_column($operators, 'created_at', 'target_id');

        $userCodes          = array_column($data, 'user_code');
        $hasBeenCashInflow  = $this->userRepo->hasBeenCashInflow($userCodes);
        $hasPayLookup       = array_flip($hasBeenCashInflow);

        foreach ($data as &$value) {
            $targetId                   = $value['id'];
            $value['type']              = trans('user.user_type.' . $value['type']);
            $adminUserId                = $adminUserIds[$targetId] ?? 0;
            $value['operate_user_name'] = $userNames[$adminUserId] ?? '';
            $value['white_add_time']    = $addTimes[$targetId] ?? '';
            $value['register_mode']     = $this->resolveRegistrationWay($value);
            $hasPay                    = isset($hasPayLookup[$value['user_code']]);
            $value['is_pay']           = $hasPay;
            $value['is_pay_name']      = trans('user.is_pay.' . ($hasPay ? 1 : 0));
        }

        return [$data, $count];
    }

    /**
     * 添加白名单
     *
     * @param int $userCode 用户ID
     *
     * @return array
     * @throws Exception
     */
    public function add($userCode)
    {
        $userInfo = $this->userRepo->firstBy('user_code', $userCode);
        //判断是否为黑名单
        if ($userInfo['type'] == User::TYPE_BLACK) {
            return ['is_succ' => false, 'message' => trans('user.black.already_black')];
        }

        $result = $this->userRepo->update(['type' => User::TYPE_TEST], $userCode, 'user_code');
        Log::info(LOGIN_USER . "添加白名单用户" . $userCode . $result);
        $this->userOperationLogRepo->addRecord($userInfo['id'], UserOperationLog::TARGET_TYPE_WHITE, UserOperationLog::DEFAULT_CONTENT, UserOperationLog::ACTION_TYPE_ADD, $userCode);

        return ['is_succ' => true, 'message' => trans('public.message.add_success')];
    }

    /**
     * 移除白名单
     *
     * @param int $userCode 用户ID
     *
     * @return array
     * @throws Exception
     */
    public function remove($userCode)
    {
        $userInfo = $this->userRepo->firstBy('user_code', $userCode);
        if (empty($userInfo) || $userInfo['type'] != User::TYPE_TEST) {
            return ['is_succ' => false, 'message' => trans('user.white.not_in_list')];
        }

        $result = $this->userRepo->update(['type' => User::TYPE_REAL], $userCode, 'user_code');
        Log::info(LOGIN_USER . "移除白名单用户" . $userCode . $result);
        $this->userOperationLogRepo->addRecord(
            $userInfo['id'],
            UserOperationLog::TARGET_TYPE_WHITE,
            UserOperationLog::DEFAULT_CONTENT,
            UserOperationLog::ACTION_TYPE_DELETE,
            $userCode
        );

        return ['is_succ' => true, 'message' => trans('public.message.del_success')];
    }

    /**
     * Determine registration method label for a single user.
     *
     * @param array $user
     *
     * @return string
     */
    private function resolveRegistrationWay(array $user): string
    {
        return User::resolveRegistrationWayLabel($user);
    }

    /**
     * 标准化日期时间格式
     *
     * @param string|null $value
     *
     * @return string|null
     */
    private function normalizeDateTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (Exception $exception) {
            return null;
        }
    }
}
