<?php

namespace App\Repositories\Api;

use App\Models\Api\Badge;
use Illuminate\Database\Eloquent\Builder;
use Scaffold\Framework\Repositories\Repository;

class BadgeRepository extends Repository
{
    public function __construct(Badge $model)
    {
        parent::__construct($model);
    }

    /**
     * 创建徽章
     */
    public function create(array $data): Badge
    {
        return $this->getQuery()->create($data);
    }

    /**
     * 根据ID查找
     */
    public function findById(int $id): ?Badge
    {
        return $this->getQuery()->find($id);
    }

    /**
     * 根据短名称查找是否已存在
     */
    public function existsByShortName(string $shortName, ?int $excludeId = null): bool
    {
        $shortName = trim($shortName);
        if ($shortName === '') {
            return false;
        }

        $query = $this->getQuery()->where('short_name', $shortName);

        if ($excludeId) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 更新徽章
     */
    public function updateById(int $id, array $data, ?Badge $badge = null): ?Badge
    {
        $badge = $badge ?: $this->findById($id);
        if (! $badge) {
            return null;
        }

        $badge->fill($data);
        $badge->save();

        return $badge;
    }

    /**
     * 徽章列表
     */
    public function getList(string $keyword, ?int $status, int $limit, int $offset): array
    {
        $query = $this->getQuery();

        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder->where('name', 'like', $this->like($keyword));
                $builder->orWhere('short_name', 'like', $this->like($keyword));
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $count = $query->count();
        $data = $query->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        return [$data, $count];
    }

    /**
     * 是否存在重名
     */
    public function existsByName(array $names, ?int $excludeId = null): bool
    {
        $names = array_filter(array_map('trim', $names), fn ($value) => $value !== '');
        if (empty($names)) {
            return false;
        }

        $query = $this->getQuery();
        $query->where(function (Builder $builder) use ($names) {
            foreach ($names as $value) {
                $builder->orWhere('name', 'like', $this->like($value));
            }
        });

        if ($excludeId) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    private function like(string $value): string
    {
        $escaped = addcslashes($value, '%_');

        return sprintf('%%%s%%', $escaped);
    }
}
