<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Models\Api\UserPointLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Scaffold\Framework\Repositories\Repository;

class UserPointLogRepository extends Repository
{
    public function __construct(UserPointLedger $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取积分明细列表
     *
     * @param int|null    $userCode 用户ID
     * @param string|null $startAt  开始时间
     * @param string|null $endAt    结束时间
     * @param int         $limit    每页条数
     * @param int         $offset   偏移量
     *
     * @return array
     */
    public function getList($userCode, $startAt, $endAt, $limit, $offset)
    {
        $baseQuery = $this->baseQuery($userCode, $startAt, $endAt);

        $count = (clone $baseQuery)->count('upl.id');

        $data = (clone $baseQuery)
            ->select(
                'upl.id',
                'upl.user_code',
                'upl.points',
                'upl.remark',
                'upl.event_key',
                'upl.created_at'
            )
            ->orderByDesc('upl.id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(static fn($item) => is_array($item) ? $item : $item->toArray())
            ->all();

        $rules = $this->fetchRuleMap(array_column($data, 'event_key'));
        foreach ($data as &$row) {
            $rule                  = $rules[$row['event_key']] ?? null;
            $row['rule_name']      = $rule['name_internal'] ?? null;
            $row['rule_task_type'] = $rule['task_type'] ?? $rule['task'] ?? null;
        }
        unset($row);

        return [$data, $count];
    }

    private function baseQuery($userCode, $startAt, $endAt): Builder
    {
        $query = $this->getQuery()
            ->from('user_point_ledger as upl');

        if (! is_null($userCode) && $userCode !== '') {
            $query->where('upl.user_code', $userCode);
        }

        if (! empty($startAt)) {
            $query->where('upl.created_at', '>=', $startAt);
        }

        if (! empty($endAt)) {
            $query->where('upl.created_at', '<=', $endAt);
        }

        return $query;
    }

    /**
     * @param array<int, string|null> $eventKeys
     * @return array<string, array{name_internal:mixed,task_type:mixed}>
     */
    private function fetchRuleMap(array $eventKeys): array
    {
        $keys = array_values(array_unique(array_filter($eventKeys)));
        if (empty($keys)) {
            return [];
        }

        $rows = DB::connection(DB_API)
            ->table('point_rule')
            ->whereIn('event_key', $keys)
            ->where('status', 1)
            ->orderByDesc('id')
            ->get(['event_key', 'name_internal', 'task_type'])
            ->map(static fn($item) => is_array($item) ? $item : (array) $item)
            ->all();

        $map = [];
        foreach ($rows as $row) {
            $eventKey = $row['event_key'] ?? null;
            if ($eventKey === null || $eventKey === '') {
                continue;
            }
            if (! isset($map[$eventKey])) {
                $map[$eventKey] = $row;
            }
        }

        return $map;
    }
}
