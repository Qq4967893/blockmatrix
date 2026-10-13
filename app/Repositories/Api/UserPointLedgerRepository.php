<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Models\Api\UserPointLedger;
use Illuminate\Database\Eloquent\Builder;
use Scaffold\Framework\Repositories\Repository;

class UserPointLedgerRepository extends Repository
{
    public function __construct(UserPointLedger $model)
    {
        parent::__construct($model);
    }

    /**
     * 根据用户计算指定事件的积分
     *
     * @param array<int> $userCodes 用户ID
     * @param string     $eventKey 积分事件
     *
     * @return array<int, float>
     */
    public function sumPointsByUserCodes(array $userCodes, string $eventKey): array
    {
        if (empty($userCodes)) {
            return [];
        }

        return $this->baseQuery($eventKey)
            ->selectRaw('upl.user_code, SUM(upl.points) as total_point')
            ->whereIn('upl.user_code', $userCodes)
            ->groupBy('upl.user_code')
            ->pluck('total_point', 'upl.user_code')
            ->map(static fn($value) => (float) $value)
            ->all();
    }

    private function baseQuery(string $eventKey): Builder
    {
        return $this->getQuery()
            ->from('user_point_ledger as upl')
            ->where('upl.event_key', $eventKey);
    }
}
