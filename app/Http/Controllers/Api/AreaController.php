<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\Api\AreaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreaController extends BaseController
{

    public function __construct(
        private AreaService $areaService
    ) {
    }

    /**
     * 获取大区列表
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function areaList()
    {
        $res = $this->areaService->areaList();

        return $this->succeed($res);
    }

    /**
     * 获取大区和下面的国家
     *
     * @param Request $request request
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function areaCountries(Request $request)
    {
        /** 大区id */
        $areaIds = $request->get('area_id', null);

        if (!empty($areaIds)) {
            $areaIds = explode(',', $areaIds);
        }
        $columns       = ['area.id', 'area.name as area_name', 'country.name', 'country.alpha'];
        $areaCountries = $this->areaService->areaCountries($columns, $areaIds);

        $currentLang = config('app.locale');
        $fallback    = config('app.fallback_locale', 'en_US');

        foreach ($areaCountries as &$item) {
            if (isset($item['area_name']) && is_string($item['area_name'])) {
                $areaNameData = json_decode($item['area_name'], true);
                if (is_array($areaNameData)) {
                    $item['area_name'] = $areaNameData[$currentLang] ?? $areaNameData[$fallback] ?? reset($areaNameData) ?? '';
                }
            }

            if (isset($item['name']) && is_string($item['name'])) {
                $nameData = json_decode($item['name'], true);
                if (is_array($nameData)) {
                    $item['name'] = $nameData[$currentLang] ?? $nameData[$fallback] ?? reset($nameData) ?? '';
                }
            }
        }

        return $this->succeed($areaCountries);
    }
}
