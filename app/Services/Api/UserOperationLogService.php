<?php

namespace App\Services\Api;

use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Repositories\Api\IbAccountRepository;

class UserOperationLogService
{
    public function __construct(
        private UserOperationLogRepository $userOperationLogRepo,
        private IbAccountRepository $ibAccountRepo,
    ){}

    /**
     * 获取 IB 上级代理变更记录
     *
     * 说明:
     * - 从 user_operation_log 表读取 target_type=IB上级代理、action_type=修改 的日志
     * - 将日志 content 中的 parent_before / parent_after 转换为接口返回结构
     *
     * @param array{
     *     user_code: int,
     *     limit?: int,
     *     offset?: int
     * } $filters
     *
     * @return array{
     *     data: array<int, array{
     *         before:string,
     *         after:string,
     *         changed_at:string,
     *         operator_name:string
     *     }>,
     *     total_count: int
     * }
     */
    public function getIbChangeLogs(array $filters): array
    {
        [$userOperationLogs, $count] = $this->userOperationLogRepo->getOperateList(UserOperationLog::TARGET_TYPE_IB_PARENT, $filters['user_code'], UserOperationLog::ACTION_TYPE_UPDATE, $filters['limit'], $filters['offset']);
        if (! $userOperationLogs){
            return [
                'data' => [],
                'total_count' => 0,
                ];
        }

        $data = [];
        foreach ($userOperationLogs as $userOperationLog){
            $content = json_decode($userOperationLog['content'], true);
            $data[] = [
                'before' => $content['parent_before']['ib_id'],
                'after' => $content['parent_after']['ib_id'],
                'changed_at' => $userOperationLog['created_at'],
                'operator_name' => $userOperationLog['login_name'],
            ];
        }
        return [
            'data' => $data,
            'total_count' => $count,
        ];
    }
}
