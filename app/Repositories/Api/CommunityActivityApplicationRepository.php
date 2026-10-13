<?php

namespace App\Repositories\Api;

use App\Models\Api\CommunityActivityApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Scaffold\Framework\Repositories\Repository;

class CommunityActivityApplicationRepository extends Repository
{
    public function __construct(CommunityActivityApplication $model)
    {
        parent::__construct($model);
    }

    /**
     * 活动报名列表
     *
     * @return array{0: Collection<int, CommunityActivityApplication>, 1: int}
     */
    public function getList(?int $activityId, string $keyword, int $limit, int $offset): array
    {
        $query = $this->getQuery()->with('activity');

        if ($activityId !== null) {
            $query->where('community_activity_id', $activityId);
        }

        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('name', 'like', $this->like($keyword))
                    ->orWhere('email', 'like', $this->like($keyword))
                    ->orWhere('message', 'like', $this->like($keyword));
            });
        }

        $count = (clone $query)->count();
        $rows = $query->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return [$rows, $count];
    }

    private function like(string $value): string
    {
        $escaped = addcslashes($value, '%_');

        return sprintf('%%%s%%', $escaped);
    }
}
