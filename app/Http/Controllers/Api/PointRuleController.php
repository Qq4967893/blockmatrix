<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\PointRule\PointRuleStatusRequest;
use App\Http\Requests\PointRule\PointRuleStoreRequest;
use App\Http\Requests\PointRule\PointRuleUpdateRequest;
use App\Services\Api\PointRuleService;
use Illuminate\Http\Request;

class PointRuleController extends BaseController
{
    public function __construct(private PointRuleService $pointRuleService)
    {
    }

    /**
     * 积分规则列表
     *
     * Query:
     * - keyword:string 内部/外部名称模糊搜索
     * - status:int 状态(-1待启用/0禁用/1启用)
     * - limit:int 每页数量(默认20)
     * - offset:int 偏移量
     *
     * Response.data[].fields 说明：
     * - id: 规则ID
     * - name_internal: 后台显示名称
     * - locale_configs: 多语言名称与描述 [{lang,name,description,pop_up_image,pop_up_text}]
     * - jump_type: 跳转类型（0=内部模块ID，1=外部链接）
     * - link: 跳转地址（内部模块ID或URL）
     *   - pop_up_image: 弹窗图片URL（各语言不同）
     *   - pop_up_text: 弹窗文案（各语言不同）
     * - event_key: 触发事件编码
     * - event_key_label: 事件文案
     * - event_params: 事件配置（如 amount/count 等）
     * - event_params_text: 事件配置文本数组
     * - relation: 条件关系（open_trade/close_trade 支持，or/and）
     * - cycle_limit: 周期内可触发次数
     * - cycle_period: 周期类型（0不限/1天/2周/3月）
     * - cycle_period_day: 周期内可触发日期（按周期类型取值；如周=1-7，月=1-31，null表示不限）
     * - cycle_period_label: 周期文案
     * - start_at / end_at: 总任务生效时间起止，格式 Y-m-d H:i:s
     * - points: 获取积分
     * - points_type: 积分类型（1=XP，2=Drop）
     * - points_type_text: 积分类型文案
     * - task_type: 任务类型
     * - task_type_label: 任务类型文案
     * - status: 状态值（-1=待启用，0=禁用，1=启用）
     * - status_label: 状态文案
     * - created_at / updated_at: 时间，格式 Y-m-d H:i:s
     */
    public function index(Request $request)
    {
        $keyword = trim($request->input('keyword', ''));
        $status = $request->input('status', null);
        $limit  = (int) $request->input('limit', 20);
        $offset = (int) $request->input('offset', 0);

        if ($status === '') {
            $status = null;
        }

        $data = $this->pointRuleService->getList($keyword, $status, $limit, $offset);

        return $this->succeed($data);
    }

    /**
     * 创建积分规则
     *
     * Body:
     * - jump_type: 跳转类型（0=内部模块ID，1=外部链接），选填
     * - link: 跳转地址（内部模块ID或http链接），选填
     * - locale_configs[].pop_up_image: 弹窗图片URL，选填
     * - locale_configs[].pop_up_text: 弹窗文案，选填
     * - points_type: 积分类型（1=XP，2=Drop），选填，默认1
     * - event_params: 事件配置；入库时按事件定义补齐全部参数键（trade_account_type 除外），未传或缺省的参数默认存 0
     *
     * @param PointRuleStoreRequest $request 入参校验
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(PointRuleStoreRequest $request)
    {
        $rule = $this->pointRuleService->create($request->validated());

        return $this->succeed($rule);
    }

    /**
     * 修改积分规则
     *
     * event_params 入库时按事件定义补齐全部参数键（trade_account_type 除外），未传或缺省的参数默认存 0
     *
     * @param PointRuleUpdateRequest $request 入参
     * @param int                    $pointRule 规则ID
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(PointRuleUpdateRequest $request, $pointRule)
    {
        $rule = $this->pointRuleService->update($pointRule, $request->validated());
        if (! $rule) {
            return $this->fail(trans('public.message.non_existent'));
        }

        return $this->succeed($rule);
    }

    /**
     * 变更积分规则状态（启用/停用）
     *
     * - status = 1 表示开启，0 表示停用，-1 表示待启用
     * - 停用后不可再次开启或切换其他状态，需新建规则
     */
    public function updateStatus(PointRuleStatusRequest $request, $pointRule)
    {
        $status = (int) $request->input('status');

        try {
            $rule = $this->pointRuleService->changeStatus($pointRule, $status);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }

        if (! $rule) {
            return $this->fail(trans('public.message.non_existent'));
        }

        return $this->succeed($rule);
    }

    /**
     * 积分事件选项
     *
     * Response.data 数组元素：
     * - key: 事件编码（用于 event_key）
     * - name: 事件名称
     * - event_params: 事件参数模板[{type,param_key,param_name,is_required,is_select_required,options?,param_value}]
     * - relation: 支持的条件关系选项（如 ["or","and"]）
     */
    public function events()
    {
        return $this->succeed($this->pointRuleService->getEventOptions());
    }

    /**
     * 积分规则辅助选项
     *
     * Response.data:
     * - cycle_limits: 循环次数选项（code/value/label）
     * - cycle_periods: 循环周期选项（code/value/label）
     * - tasks: 任务类型选项（code/value/label）
     * - points_types: 积分类型选项（code/label）
     */
    public function options()
    {
        return $this->succeed($this->pointRuleService->getRuleOptions());
    }
}
