<?php

namespace App\Services\Api;

use App\Repositories\Api\AreaRepository;
use App\Repositories\Api\CountryRepository;

class AreaService
{
    public function __construct(
        private AreaRepository $repo,
        private CountryRepository $countryRepo
    ) {}

    /**
     * 大区列表
     *
     * @return array
     *
     * @throws \Exception
     */
    public function areaList()
    {
        return $this->repo->areaList();
    }

    /**
     * 获取所有大区和大区下国家
     *
     * @param  array  $columns  查询字段
     * @param  array  $areaIds  大区id
     * @return array
     */
    public function areaCountries($columns, $areaIds = [])
    {
        return $this->repo->areaCountries($columns, $areaIds);
    }

    /**
     * 获取国家/地区
     *
     * @param  string  $worldCode  国家代码
     * @return array
     */
    public function getCountry($worldCode)
    {
        $worldCodeArr = explode(',', $worldCode);
        $worldName = $this->countryRepo->getNamesByAlphas($worldCodeArr);
        $fallback = config('app.fallback_locale', 'en_US');

        foreach ($worldName as &$jsonName) {
            $names = json_decode($jsonName, true);

            if (is_array($names)) {
                $jsonName = $names[$fallback] ?? reset($names) ?: '';
            }
        }

        return $worldName;
    }
}
