<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Badge\BadgeStatusRequest;
use App\Http\Requests\Badge\BadgeStoreRequest;
use App\Http\Requests\Badge\BadgeUpdateRequest;
use App\Services\Api\BadgeService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Badge', '徽章管理')]
class BadgeController extends BaseController
{
    private const EVENT_PARAMS_DESCRIPTION = '事件参数。先调用 GET /api/badges/events，根据 event_key 对应 event_params[].param_key 组装键值对；is_required=true 的参数必须传，is_select_required=true 的参数值必须来自 options。master_level_changed 可传选中的等级 param_key 数组，如 ["1", "3"]，响应仍保持以等级 param_key 为键的对象格式。';

    private const BADGE_RESPONSE_TYPE = 'array{id:int,icon:string,short_name:string,event_key:string,event_key_text:string,event_params:array<string, mixed>,event_params_text:array<int, string>,event_text:string,relation:?string,status:int,status_text:string,locale_configs:array<int, array{lang:string,name:string,description:string}>,created_at:?string,updated_at:?string}';

    public function __construct(private BadgeService $badgeService) {}

    #[QueryParameter(name: 'keyword', description: '徽章名称或简称关键词', type: 'string', required: false)]
    #[QueryParameter(name: 'status', description: '状态：0=禁用，1=启用', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[ScrambleResponse(
        description: '徽章列表',
        type: 'array{data: array<int, array{id:int,icon:string,short_name:string,event_key:string,event_key_text:string,event_params:array<string, mixed>,event_params_text:array<int, string>,event_text:string,relation:?string,status:int,status_text:string,locale_configs:array<int, array{lang:string,name:string,description:string}>,created_at:?string,updated_at:?string}>, total_count:int}'
    )]
    /**
     * 获取徽章列表
     *
     * Query:
     * - keyword:string 关键词，按徽章简称等模糊筛选
     * - status:int 状态
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * @param  Request  $request  request
     */
    public function index(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->input('keyword', ''));
        $status = $request->input('status', null);
        $limit = (int) $request->input('limit', 20);
        $offset = (int) $request->input('offset', 0);

        if ($status === '') {
            $status = null;
        } elseif ($status !== null) {
            $status = (int) $status;
        }

        $data = $this->badgeService->getList($keyword, $status, $limit, $offset);

        return $this->succeed($data);
    }

    #[Endpoint('badge.store', title: '新增徽章')]
    #[BodyParameter('icon', '图标地址或存储键', required: true, type: 'string')]
    #[BodyParameter('short_name', '徽章简称，唯一', required: true, type: 'string')]
    #[BodyParameter('event_key', '事件标识，取值来自 GET /api/badges/events 返回的 key', required: true, type: 'string', example: 'close_trade')]
    #[BodyParameter('event_params', self::EVENT_PARAMS_DESCRIPTION, required: false, type: 'array<int, string>|object', example: ['trade_account_type' => 1, 'lots' => 1])]
    #[BodyParameter('relation', '事件参数关系；仅 events 返回 relation 选项的事件必传，目前 close_trade 支持 or/and', required: false, type: 'string', example: 'and')]
    #[BodyParameter('locale_configs', '多语言配置，至少一项', required: true, type: 'array<int, array{lang:string,name:string,description?:string}>')]
    #[ScrambleResponse(description: '新增后的徽章', type: self::BADGE_RESPONSE_TYPE)]
    /**
     * 新增徽章
     *
     * @param  BadgeStoreRequest  $request  request
     */
    public function store(BadgeStoreRequest $request): JsonResponse
    {
        $badge = $this->badgeService->create($request->validated());

        return $this->succeed($badge);
    }

    #[Endpoint('badge.update', title: '修改徽章')]
    #[PathParameter('badge', description: '徽章ID', type: 'integer')]
    #[BodyParameter('icon', '图标地址或存储键（可选，缺省保留原值）', required: false, type: 'string')]
    #[BodyParameter('short_name', '徽章简称，唯一', required: true, type: 'string')]
    #[BodyParameter('event_key', '事件标识，取值来自 GET /api/badges/events 返回的 key', required: true, type: 'string', example: 'close_trade')]
    #[BodyParameter('event_params', self::EVENT_PARAMS_DESCRIPTION, required: false, type: 'array<int, string>|object', example: ['trade_account_type' => 1, 'lots' => 1])]
    #[BodyParameter('relation', '事件参数关系；仅 events 返回 relation 选项的事件必传，目前 close_trade 支持 or/and', required: false, type: 'string', example: 'and')]
    #[BodyParameter('locale_configs', '多语言配置，至少一项', required: true, type: 'array<int, array{lang:string,name:string,description?:string}>')]
    #[ScrambleResponse(description: '修改后的徽章', type: self::BADGE_RESPONSE_TYPE)]
    /**
     * 修改徽章
     *
     * @param  BadgeUpdateRequest  $request  request
     * @param  int  $badge  徽章ID
     */
    public function update(BadgeUpdateRequest $request, int $badge): JsonResponse
    {
        $result = $this->badgeService->update($badge, $request->validated());

        if ($result === null) {
            return $this->fail(trans('public.message.non_existent'));
        }
        if ($result === false) {
            return $this->fail(trans('public.message.data_error'));
        }

        return $this->succeed($result);
    }

    /**
     * 修改徽章状态
     *
     * @param  BadgeStatusRequest  $request  request
     * @param  int  $badge  徽章ID
     */
    public function updateStatus(BadgeStatusRequest $request, int $badge): JsonResponse
    {
        $status = (int) $request->input('status');

        $result = $this->badgeService->changeStatus($badge, $status);
        if ($result === null) {
            return $this->fail(trans('public.message.non_existent'));
        }
        if ($result === false) {
            return $this->fail(trans('public.message.data_error'));
        }

        return $this->succeed();
    }

    /**
     * 获取徽章事件选项
     *
     * Response.data 数组元素：
     * - key: 事件编码（用于 event_key）
     * - name: 事件名称
     * - event_params: 事件参数模板[{type,param_key,param_name,is_required,is_select_required,options?}]
     * - relation: 支持的条件关系选项（如 ["or","and"]）
     * - render_mode: 参数渲染模式（1常规参数配置/2组合参数配置）
     */
    #[ScrambleResponse(
        description: '徽章事件及参数选项',
        type: 'array<int, array{key:string,name:string,event_params:array<int, array{type:string,param_key:string,param_name:string,is_required?:bool,is_select_required?:bool,options?:array<int, array{value:int,label:string}>}>,relation:array<int, string>,render_mode:int}>'
    )]
    public function events(): JsonResponse
    {
        return $this->succeed($this->badgeService->getEventOptions());
    }
}
