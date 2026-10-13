<?php

namespace App\Repositories\Api;

use App\Models\Api\TemplateLayout;
use Scaffold\Framework\Repositories\Repository;

class TemplateLayoutRepository extends Repository
{
    public function __construct(TemplateLayout $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取layout模版列表
     *
     * @param string $sort
     * @param string $order
     * @param int $limit
     * @param int $offset
     *
     * @return array
     */
    public function getList($sort, $order, $limit, $offset)
    {
        $query = $this->getQuery();
        $count = $query->count();
        $data = $query->orderBy($sort, $order)->offset($offset)->limit($limit)->get()->toArray();
        return [$data, $count];
    }

    /**
     * 获取所有layout模版
     *
     * @param int $status
     *
     * @return array
     */
    public function getAll($status)
    {
        return $this->getQuery()->where('status', $status)->get()->toArray();
    }
}
