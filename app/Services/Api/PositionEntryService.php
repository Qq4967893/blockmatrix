<?php

namespace App\Services\Api;

use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\PositionEntryRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use Illuminate\Support\Facades\Log;

class PositionEntryService
{
    private const STATUS_DISABLED = 0;

    public function __construct(
        private PositionEntryRepository $repo,
        private AdminUserRepository $adminUserRepo,
        private UserOperationLogRepository $userOperationLogRepo
    ) {}

    /**
     * 获取广告位信息
     *
     * @param  string  $sort  排序字段
     * @param  string  $order  排序方式(desc:降序, asc:升序)
     * @param  int|null  $status  状态
     * @return array
     */
    public function entryList($sort, $order, ?int $status = null)
    {
        $entryArr = $this->repo->peList($sort, $order, $status);
        if (! empty($entryArr['data'])) {
            $userIds = array_column($entryArr['data'], 'id');

            $operators = $this->userOperationLogRepo->getBatchOperateInfo(UserOperationLog::TARGET_TYPE_AD, $userIds, [UserOperationLog::ACTION_TYPE_ADD, UserOperationLog::ACTION_TYPE_UPDATE]);
            $adminUserIds = array_column($operators, 'operator_id', 'target_id');
            $userNames = $this->adminUserRepo->getColumnByIds($adminUserIds, 'login_name');

            foreach ($entryArr['data'] as &$value) {
                $value['operate_name'] = $userNames[$value['id']] ?? '';
                $value['status'] = (int) ($value['status'] ?? 0);
            }
            unset($value);
        }

        $entryArr['total_count'] = $entryArr['total'] ?? count($entryArr['data'] ?? []);

        return $entryArr;
    }

    /**
     * 检测identifier的唯一性
     *
     * @param  string  $identifier  唯一标识
     * @param  int|null  $excludeId  排除的广告位ID
     * @return object
     */
    public function identifierUnique($identifier, ?int $excludeId = null)
    {
        return $this->repo->identifierUnique($identifier, $excludeId);
    }

    /**
     * 添加广告位
     *
     * @param  string  $description  描述
     * @param  string  $name  名称
     * @param  string  $identifier  区分标识
     * @param  int  $status  状态
     * @return bool
     *
     * @throws \Exception
     */
    public function addEntry($description, $name, $identifier, int $status)
    {
        $insert = [
            'description' => $description,
            'name' => $name,
            'identifier' => $identifier,
            'status' => $status,
        ];
        Log::info(LOGIN_USER.'添加广告位'.$identifier);
        $this->userOperationLogRepo->addRecord(0, UserOperationLog::TARGET_TYPE_AD, json_encode($insert, JSON_UNESCAPED_UNICODE));

        return $this->repo->insert($insert);
    }

    /**
     * 修改广告位
     *
     * @param  int  $positionId  编辑广告位id
     * @param  string  $description  描述
     * @param  string  $name  名称
     * @param  string  $identifier  区分标识
     * @param  int|null  $status  状态
     * @return bool
     */
    public function updateEntry($positionId, $description, $name, $identifier, ?int $status = null)
    {
        $update = [
            'description' => $description,
            'name' => $name,
            'identifier' => $identifier,
        ];
        if ($status !== null) {
            $update['status'] = $status;
        }
        Log::info(LOGIN_USER.'修改广告位'.$identifier);
        $this->userOperationLogRepo->addRecord($positionId, UserOperationLog::TARGET_TYPE_AD, json_encode($update, JSON_UNESCAPED_UNICODE), UserOperationLog::ACTION_TYPE_UPDATE);

        return $this->repo->update($update, $positionId);
    }

    /**
     * 删除广告位
     *
     * @param  int  $positionId  广告位id
     * @return void
     */
    public function deleteEntry($positionId)
    {
        Log::info(LOGIN_USER.'删除广告位'.$positionId);
        $this->userOperationLogRepo->addRecord($positionId, UserOperationLog::TARGET_TYPE_AD, '{}', UserOperationLog::ACTION_TYPE_DELETE);

        $this->repo->update(['status' => self::STATUS_DISABLED], $positionId);
    }
}
