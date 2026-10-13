<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Models\Api\IbLevelChangeLog;
use Scaffold\Framework\Repositories\Repository;

class IbLevelChangeLogRepository extends Repository
{
    public function __construct(IbLevelChangeLog $model)
    {
        parent::__construct($model);
    }

    /**
     * 分页获取指定用户的 IB 等级变更日志
     *
     * @param array{
     *     user_code: int,
     *     limit?: int,
     *     offset?: int
     * } $filters
     *
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    public function getList(array $filters): array
    {
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $limit = max(1, (int) ($filters['limit'] ?? 20));

        $query = $this->getQuery()
            ->from('ib_level_change_log')
            ->where('ib_user_code', (int) $filters['user_code']);

        $total = (clone $query)->count('id');
        $data = $query->orderByDesc('assessed_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        return [$data, $total];
    }

    public function createLog(array $payload): IbLevelChangeLog
    {
        /** @var IbLevelChangeLog $log */
        $log = $this->create($payload);

        return $log;
    }
}
