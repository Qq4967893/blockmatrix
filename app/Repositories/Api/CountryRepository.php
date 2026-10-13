<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Models\Api\Country;
use Scaffold\Framework\Repositories\Repository;

class CountryRepository extends Repository
{
    public function __construct(Country $model)
    {
        parent::__construct($model);
    }

    /**
     * 按 alpha 编码批量获取国家
     *
     * @param  array  $alphas  国家编码
     * @param  array  $columns  查询字段
     * @return array
     */
    public function getByAlphas(array $alphas, array $columns = ['alpha', 'name'])
    {
        if (empty($alphas)) {
            return [];
        }

        return $this->getQuery()
            ->select($columns)
            ->whereIn('alpha', $alphas)
            ->get()
            ->toArray();
    }

    /**
     * @return string[]
     */
    public function getNamesByAlphas(array $alphas): array
    {
        return $this->getQuery()
            ->whereIn('alpha', $alphas)
            ->pluck('name')
            ->all();
    }
}
