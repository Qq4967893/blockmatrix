<?php

namespace App\Repositories\Api;

use App\Models\Api\PositionRecord;
use Scaffold\Framework\Repositories\Repository;

class PositionRecordRepository extends Repository
{
    public function __construct(
        PositionRecord $model
    ) {
        parent::__construct($model);
    }

    /**
     * 获取广告记录列表
     *
     * @param array $filters 过滤条件
     *
     * @return array
     */
    public function listByFilters(array $filters = [])
    {
        $query = $this->getQuery();

        $identifier = trim((string) ($filters['identifier'] ?? ''));
        if ($identifier !== '') {
            $query->where('identifier', '=', $identifier);
        }

        $name = trim((string) ($filters['name'] ?? ''));
        if ($name !== '') {
            $query->where('name', 'like', '%' . $name . '%');
        }

        if (($status = $filters['status'] ?? null) !== null && $status !== '') {
            $query->where('status', '=', (int) $status);
        }

        $platforms = $filters['platform'] ?? [];
        if (is_array($platforms)) {
            $platforms = array_values(array_filter(array_map(function ($item) {
                return is_scalar($item) ? trim((string) $item) : '';
            }, $platforms), function ($item) {
                return $item !== '';
            }));
            if (! empty($platforms)) {
                $this->applyPlatformFilter($query, $platforms);
            }
        }

        return $query
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->get()
            ->toArray();
    }

    /**
     * 根据广告位标识和平台获取广告记录
     *
     * @param string                    $identifier 广告位标识
     * @param string|null               $platform   平台
     * @param int|null                  $excludeId  排除记录
     * @param int|array<int, int>|null $status     状态
     *
     * @return array
     */
    public function listByIdentifierPlatform(
        string $identifier,
        ?string $platform = null,
        ?int $excludeId = null,
        int|array|null $status = null
    ): array
    {
        $query = $this->getQuery()->where('identifier', '=', $identifier);

        if ($platform !== null && $platform !== '') {
            $this->applyPlatformFilter($query, [$platform]);
        }

        if ($excludeId !== null) {
            $query->where('id', '<>', $excludeId);
        }

        if (is_array($status)) {
            $query->whereIn('status', $status);
        } elseif ($status !== null) {
            $query->where('status', '=', $status);
        }

        return $query
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->get()
            ->toArray();
    }

    /**
     * 下架已经到达结束时间的排期广告。
     */
    public function disableExpired(string $now): int
    {
        return $this->getQuery(true)
            ->whereIn('status', PositionRecord::SCHEDULED_STATUSES)
            ->whereNotNull('end_time')
            ->where('end_time', '<=', $now)
            ->update(['status' => PositionRecord::STATUS_DISABLED]);
    }

    /**
     * 上架已经到达开始时间且尚未结束的待上架广告。
     */
    public function enableStarted(string $now): int
    {
        return $this->getQuery(true)
            ->where('status', PositionRecord::STATUS_PENDING)
            ->whereNotNull('start_time')
            ->where('start_time', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('end_time')
                    ->orWhere('end_time', '>', $now);
            })
            ->update(['status' => PositionRecord::STATUS_ENABLED]);
    }

    /**
     * 按平台筛选，兼容 platform 存单平台或逗号分隔多平台。
     *
     * @param mixed              $query     查询对象
     * @param array<int, string> $platforms 平台
     */
    private function applyPlatformFilter(mixed $query, array $platforms): void
    {
        $platforms = array_values(array_unique(array_filter(array_map(function ($platform) {
            return is_scalar($platform) ? trim((string) $platform) : '';
        }, $platforms), function ($platform) {
            return $platform !== '';
        })));

        if (empty($platforms)) {
            return;
        }

        $query->where(function ($platformQuery) use ($platforms) {
            foreach ($platforms as $platform) {
                $platformQuery->orWhereRaw('FIND_IN_SET(?, platform)', [$platform]);
            }
        });
    }
}
