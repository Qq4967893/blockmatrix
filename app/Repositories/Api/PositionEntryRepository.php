<?php

namespace App\Repositories\Api;

use Illuminate\Database\Eloquent\Model;
use Scaffold\Framework\Models\Api\PositionEntry;
use Scaffold\Framework\Repositories\Repository;

class PositionEntryRepository extends Repository
{
    public function __construct(
        PositionEntry $model
    ) {
        parent::__construct($model);
    }

    /**
     * 获取广告位列表
     *
     * @param  string  $sort  排序字段
     * @param  string  $order  排序方式(desc:降序, asc:升序)
     * @param  int|null  $status  状态
     * @return array
     */
    public function peList($sort, $order, ?int $status = null)
    {
        $query = $this->getQuery();
        if ($status !== null) {
            $query->where('status', '=', $status);
        }

        $data['total_count'] = $query->count();
        $data['data'] = $query->orderBy($sort, $order)->get()->toArray();

        return $data;
    }

    /**
     * 检测identifier的唯一性
     *
     * @param  string  $identifier  唯一标识
     * @param  int|null  $excludeId  排除的广告位ID
     * @return PositionEntry|null|Model
     */
    public function identifierUnique($identifier, ?int $excludeId = null)
    {
        $query = $this->getQuery()->where('identifier', '=', $identifier);
        if ($excludeId !== null) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->first();
    }

    /**
     * 根据标识获取广告位
     *
     * @param string $identifier 广告位标识
     * @return PositionEntry|null|Model
     */
    public function firstByIdentifier(string $identifier)
    {
        return $this->getQuery()
            ->where('identifier', '=', $identifier)
            ->first();
    }
}
