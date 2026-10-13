<?php

namespace App\Repositories\Api;

use App\Models\Api\PositionTab;
use Scaffold\Framework\Repositories\Repository;

class PositionTabRepository extends Repository
{
    public function __construct(
        PositionTab $model
    ) {
        parent::__construct($model);
    }

    /**
     * 获取标签列表
     *
     * @param int    $entryId 条目ID
     * @param string $sort    排序字段
     * @param string $order   排序方式(desc:降序, asc:升序)
     *
     * @return array
     */
    public function tList($entryId, $sort, $order)
    {
        $tabArr        = $this->getQuery()->where('entry_id', '=', $entryId);
        $data['total_count'] = $tabArr->count();
        $data['data']  = $tabArr->orderBy($sort, $order)->get()->toArray();

        return $data;
    }
}
