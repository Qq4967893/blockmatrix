<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Models\Api\MessageSystemRead;
use Illuminate\Support\Collection;
use Scaffold\Framework\Repositories\Repository;

class MessageSystemReadRepository extends Repository
{
    public function __construct(MessageSystemRead $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取消息的最新阅读状态
     *
     * @param array $messageIds
     *
     * @return array<int,array<string,mixed>>
     */
    public function getLatestStatusesByMessageIds(array $messageIds): array
    {
        if (empty($messageIds)) {
            return [];
        }

        /** @var Collection $records */
        $records = $this->getQuery()
            ->whereIn('sid', $messageIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('sid')
            ->map(function (Collection $rows) {
                $row = $rows->first();

                return [
                    'status'  => $row ? 1 : 0,
                    'is_read' => (bool) $row,
                ];
            });

        return $records->toArray();
    }
}
