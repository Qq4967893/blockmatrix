<?php

namespace App\Repositories\Api;

use App\Models\Api\AppRelease;
use Scaffold\Framework\Repositories\Repository;

class AppReleaseRepository extends Repository
{
    public function __construct(AppRelease $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取每个平台最新一条版本记录（按 id 最大值）。
     *
     * @return array<int, array<string,mixed>>
     */
    public function listLatestByPlatform(): array
    {
        $query = $this->getQuery()->from('app_release as ar')
            ->join(
                \DB::raw('(SELECT platform, MAX(id) AS max_id FROM app_release GROUP BY platform) latest'),
                'ar.id',
                '=',
                'latest.max_id'
            )
            ->orderByDesc('ar.updated_at')
            ->orderByDesc('ar.id')
            ->select('ar.*');

        return $query
            ->get()
            ->map(static fn ($item) => is_array($item) ? $item : $item->toArray())
            ->all();
    }

    public function existsUnique(string $platform, string $version, int $buildNumber): bool
    {
        return $this->getQuery()
            ->where('platform', $platform)
            ->where('version', $version)
            ->where('build_number', $buildNumber)
            ->exists();
    }

    public function createRelease(array $payload): int
    {
        $model = $this->getQuery()->create($payload);

        return (int) ($model->id ?? 0);
    }

    public function findById(int $id): ?AppRelease
    {
        $row = $this->getQuery()->find($id);

        return $row instanceof AppRelease ? $row : null;
    }

    public function updateRelease(AppRelease $model, array $payload): bool
    {
        $model->fill($payload);

        return (bool) $model->save();
    }
}
