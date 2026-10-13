<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\Api\PositionTabService;
use Illuminate\Http\Request;

class PositionTabController extends BaseController
{
    public function __construct(
        private PositionTabService $pTabService,
    ) {
    }

    /**
     * 获取标签列表
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function tabList(Request $request)
    {
        /** 条目id */
        $entryId = $request->input('entry_id', '');
        /** 排序字段 */
        $sort    = $request->input('sort', 'id');
        /** 排序方式(desc:降序, asc:升序) */
        $order   = $request->input('order', 'desc');

        $tabArr  = $this->pTabService->tabList($entryId, $sort, $order);
        if ($tabArr) {
            return $this->succeed($tabArr);
        } else {
            return $this->fail(trans('public.message.data_empty'));
        }
    }

    /**
     * 添加标签
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(Request $request)
    {
        /** 条目id */
        $entryId  = $request->input('entry_id', '');
        /** 描述 */
        $desc     = $request->input('desc', '');

        if (!$entryId || !$desc) {
            return $this->fail(trans('public.message.Incomplete'));
        }
        $bool = $this->pTabService->addTab($desc, $entryId);
        if ($bool) {
            return $this->succeed();
        } else {
            return $this->fail(trans('public.message.add_error'));
        }
    }

    /**
     * 修改标签
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        /** 标签id */
        $tabId = $request->input('tab_id', '');
        /** 描述 */
        $desc  = $request->input('desc', '');

        if (!$tabId || !$desc) {
            return $this->fail(trans('public.message.Incomplete'));
        }
        $bool = $this->pTabService->updateTab($desc, $tabId);
        if ($bool) {
            return $this->succeed();
        } else {
            return $this->fail(trans('public.message.upd_error'));
        }
    }

    /**
     * 删除标签
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function del(Request $request)
    {
        /** 标签id */
        $tabId = $request->input('tab_id', '');
        if (!$tabId) {
            return $this->fail(trans('public.message.id_empty'));
        }
        $bool = $this->pTabService->deleteTab($tabId);
        if ($bool) {
            return $this->succeed();
        } else {
            return $this->fail(trans('public.message.del_error'));
        }
    }
}
