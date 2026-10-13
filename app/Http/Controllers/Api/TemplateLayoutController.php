<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Template\AddTemplateLayoutRequest;
use App\Http\Requests\Template\UpdateTemplateLayoutRequest;
use App\Models\Api\TemplateLayout;
use App\Services\Api\TemplateLayoutService;
use App\Services\Api\TemplateNoticeService;
use Illuminate\Http\Request;

class TemplateLayoutController extends BaseController
{

    public function __construct(
        private TemplateLayoutService $templateLayoutService,
        private TemplateNoticeService $templateNoticeService,
    ) {
    }

    /**
     * layout模版列表
     *
     * Response.data[].fields:
     * - id:int ID
     * - name:string layout模版名称
     * - content:string 内容
     * - status：int 状态：0未发布，1已发布
     * - updated_at:string 最后一次修改时间
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        $sort = $request->input('sort', 'created_at'); //默认创建时间
        $order = $request->input('order', 'desc'); //默认倒序
        $limit  = $request->input('limit', 20);
        $offset = $request->input('offset', 0);

        [$data, $count] = $this->templateLayoutService->getList($sort, $order, $limit, $offset);
        return $this->succeed(['data' => $data, 'total_count' => $count]);
    }

    /**
     * 获取所有layout模版
     *
     * Query:
     * - status:int 状态：0未发布，1已发布
     *
     * Response.data[].fields:
     * - id:int ID
     * - name:string layout模版名称
     * - content:string 内容
     * - status：int 状态：0未发布，1已发布
     * - updated_at:string 最后一次修改时间
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function all(Request $request)
    {
        $status = $request->input('status', TemplateLayout::STATUS_PUBLISHED);

        $data = $this->templateLayoutService->getAll($status);
        return $this->succeed($data);
    }

    /**
     * 获取layout模版
     *
     * @param int $id layout模版ID
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function info($id)
    {
        $templateLayout = $this->templateLayoutService->getFirst($id);
        if (is_null($templateLayout)) {
            return $this->fail(trans('public.message.param_non_existent', ['param' => 'id']));
        }
        return $this->succeed($templateLayout);
    }

    /**
     * 添加邮件layout
     *
     * Query:
     * - name:string layout名称
     * - content:string 内容
     * - status:int 状态：0未发布，1已发布
     *
     * @param AddTemplateLayoutRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function add(AddTemplateLayoutRequest $request)
    {
        $name = $request->input('name');
        $content = $request->input('content');
        $status = $request->input('status');

        // 判断content内是否包含{{content}}
        if (! str_contains($content, TemplateLayout::CONTENT_PLACEHOLDER)) {
            return $this->fail(trans('template.message.missing_keyword'));
        }
        $result = $this->templateLayoutService->add($name, $content, $status);
        if (! $result) {
            return $this->fail(trans('public.message.add_fail'));
        }
        return $this->succeed([], trans('public.message.add_success'));
    }

    /**
     * 修改layout模版
     *
     * Query:
     * - id:int layout ID
     * - name:string layout名称
     * - content:string 内容
     * - status:int 状态：0未发布，1已发布
     *
     * @param UpdateTemplateLayoutRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdateTemplateLayoutRequest $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $content = $request->input('content');
        $status = $request->input('status');

        // 判断content内是否包含{{content}}
        if (! str_contains($content, TemplateLayout::CONTENT_PLACEHOLDER)) {
            return $this->fail(trans('template.message.missing_keyword'));
        }

        // 判断layout是否存在
        $layoutObj = $this->templateLayoutService->getFirst($id);
        if (! $layoutObj) {
            return $this->fail(trans('public.message.non_existent'));
        }

        $bool = $this->templateLayoutService->update($id, $name, $content, $status);
        if (! $bool) {
            return $this->fail(trans('public.message.upd_error'));
        }
        return $this->succeed([], trans('public.message.upd_success'));
    }

    /**
     * 删除layout模版
     *
     * @param int $id layout模版ID
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id)
    {
        // 如果layout模版正在使用，无法删除
        $templateNotice = $this->templateNoticeService->getFirst(0, $id);
        if (! is_null($templateNotice)) {
            return $this->fail(trans('template.message.layout_used'));
        }
        $this->templateLayoutService->delete($id);
        return $this->succeed([], trans('public.message.del_success'));
    }
}
