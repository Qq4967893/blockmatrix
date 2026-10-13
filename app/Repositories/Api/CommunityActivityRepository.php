<?php

namespace App\Repositories\Api;

use App\Models\Api\CommunityActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Scaffold\Framework\Repositories\Repository;

class CommunityActivityRepository extends Repository
{
    public function __construct(CommunityActivity $model)
    {
        parent::__construct($model);
    }

    /**
     * 活动列表
     *
     * @return array{0: Collection<int, CommunityActivity>, 1: int}
     */
    public function getList(string $keyword, ?int $type, ?int $status, ?string $city, int $limit, int $offset, ?Carbon $now = null): array
    {
        $query = $this->getQuery();
        $nowString = ($now ?? Carbon::now())->toDateTimeString();

        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder->where('name', 'like', $this->like($keyword))
                    ->orWhere('summary', 'like', $this->like($keyword));
            });
        }

        if ($type !== null) {
            $query->where('type', $type);
        }

        if ($status !== null) {
            $query->where(function (Builder $builder) use ($status, $nowString): void {
                match ($status) {
                    CommunityActivity::STATUS_PENDING => $builder
                        ->where('status', '<>', CommunityActivity::STATUS_VOIDED)
                        ->where('status', '<>', CommunityActivity::STATUS_ONLINE)
                        ->where('start_at', '>', $nowString),
                    CommunityActivity::STATUS_ONLINE => $builder
                        ->where(function (Builder $online) use ($nowString): void {
                            $online->where(function (Builder $byTime) use ($nowString): void {
                                $byTime->where('status', '<>', CommunityActivity::STATUS_VOIDED)
                                    ->where('start_at', '<=', $nowString)
                                    ->where('end_at', '>=', $nowString);
                            })->orWhere(function (Builder $byManual) use ($nowString): void {
                                $byManual->where('status', CommunityActivity::STATUS_ONLINE)
                                    ->where('end_at', '>=', $nowString);
                            });
                        }),
                    CommunityActivity::STATUS_VOIDED => $builder
                        ->where(function (Builder $voided) use ($nowString): void {
                            $voided->where('status', CommunityActivity::STATUS_VOIDED)
                                ->orWhere('end_at', '<', $nowString);
                        }),
                    default => $builder->whereRaw('1 = 0'),
                };
            });
        }

        if ($city !== null && $city !== '') {
            $query->where('city', $city);
        }

        $count = (clone $query)->count();
        $rows = $query->orderByRaw(
            'CASE WHEN status = ? OR end_at < ? THEN 1 ELSE 0 END',
            [CommunityActivity::STATUS_VOIDED, $nowString]
        )
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return [$rows, $count];
    }

    public function create(array $data): CommunityActivity
    {
        return $this->getQuery()->create($data);
    }

    public function findById(int $id): ?CommunityActivity
    {
        $activity = $this->getQuery()->find($id);

        return $activity instanceof CommunityActivity ? $activity : null;
    }

    public function findByIdForUpdate(int $id): ?CommunityActivity
    {
        $activity = $this->getQuery(true)
            ->lockForUpdate()
            ->find($id);

        return $activity instanceof CommunityActivity ? $activity : null;
    }

    public function updateById(int $id, array $data, ?CommunityActivity $activity = null): ?CommunityActivity
    {
        $activity = $activity ?: $this->findById($id);
        if (! $activity) {
            return null;
        }

        $activity->fill($data);
        $activity->save();

        return $activity;
    }

    /**
     * 根据活动时间自动上线/作废。
     *
     * @return array{voided: int, online: int}
     */
    public function updateStatusesByActivityTime(Carbon $now): array
    {
        $model = $this->getQuery()->getModel();
        $now = $now->copy();
        $nowString = $now->toDateTimeString();
        $updatedAt = $model->freshTimestampString();

        $voided = $model->newQuery()
            ->where('status', '<>', CommunityActivity::STATUS_VOIDED)
            ->where('end_at', '<', $nowString)
            ->update([
                'status' => CommunityActivity::STATUS_VOIDED,
                'updated_at' => $updatedAt,
            ]);

        $online = $model->newQuery()
            ->where('status', CommunityActivity::STATUS_PENDING)
            ->where('start_at', '<=', $nowString)
            ->where('end_at', '>=', $nowString)
            ->update([
                'status' => CommunityActivity::STATUS_ONLINE,
                'updated_at' => $updatedAt,
            ]);

        return [
            'voided' => $voided,
            'online' => $online,
        ];
    }

    private function like(string $value): string
    {
        $escaped = addcslashes($value, '%_');

        return sprintf('%%%s%%', $escaped);
    }
}
