<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Repositories\Repository;
use App\Models\Api\Area;

class AreaRepository extends Repository
{
    public function __construct(Area $model)
    {
        parent::__construct($model);
    }

    /**
     * 大区列表
     *
     * @return array
     * @throws \Exception
     */
    public function areaList()
    {
        return $this->getQuery()->select('id', 'name')->get()->toArray();
    }

    /**
     * 获取所有大区名称
     *
     * @return array
     */
    public function getAllAreaNames()
    {
        return $this->getQuery()->pluck('name', 'id')->toArray();
    }

    /**
     * 批量获取大区信息
     *
     * @param array  $ids     大区ID数组
     * @param array  $columns 查询字段
     * @param string $pluck   输出字段
     *
     * @return array
     */
    public function getBatchColumns($ids, $columns = ['*'], $pluck = null)
    {
        $query = $this->getQuery()->whereIn('id', $ids)->select($columns);
        if (!is_null($pluck)) {
            if ($pluck === 'name') {
                $areas  = $query->get();
                $result = [];
                foreach ($areas as $area) {
                    $result[$area->id] = $area->name;
                }

                return $result;
            }

            return $query->pluck($pluck, 'id')->toArray();
        } else {
            return $query->get()->toArray();
        }
    }

    /**
     * 通过id获取大区信息（默认获取大区名）
     *
     * @param int    $id     大区ID
     * @param string $column 获取字段
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|mixed|object|null
     */
    public function getById($id, $column = '')
    {
        if (empty($column)) {
            return $this->getQuery()->where('id', $id)->first();
        }

        return $this->getQuery()->where('id', $id)->value($column);
    }

    /**
     * 获取所有大区的国家
     *
     * @param array $columns 查询字段
     * @param array $areaIds 大区id
     *
     * @return array
     */
    public function areaCountries($columns, $areaIds = [])
    {
        $query = $this->getQuery()->select($columns);

        if (! empty($areaIds)) {
            $query->whereIn('country.area_id', $areaIds);
        }

        return $query->leftJoin('country', 'area.id', '=', 'country.area_id')->get()->toArray();
    }
}
