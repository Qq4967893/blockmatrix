<?php


namespace App\Services\Blockmatrix;

use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Blockmatrix\UserOperationLogRepository;

class UserOperationLogService
{
    public function __construct(
        private UserOperationLogRepository $repo,
    )
    {
    }

    /**
     * 获取操作人
     *
     * @param int $type 内容类型
     * @param int $status 状态
     * @param int $operateTargetId 操作内容ID
     *
     * @return object|null
     */
    public function getOperator($type, $status, $operateTargetId)
    {
        return $this->repo->getOperator($type, $status, $operateTargetId);
    }

    /**
     * 新增操作日志
     *
     * @param int    $targetId   操作内容ID
     * @param int    $targetType 内容类型
     * @param string $content    操作内容
     * @param int    $actionType 操作类型
     * @param int    $userCode   用户ID
     *
     * @return bool
     */
    public function addRecord($targetId, $targetType, $content, $actionType = UserOperationLog::ACTION_TYPE_ADD, $userCode = null)
    {
        return $this->repo->addRecord($targetId, $targetType, $content, $actionType, $userCode);
    }
}
