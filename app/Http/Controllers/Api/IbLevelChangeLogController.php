<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\Api\IbLevelChangeLogService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('IB Level Change Log', 'IB等级变更记录（ib_level_change_log）')]
class IbLevelChangeLogController extends BaseController
{
    public function __construct(private IbLevelChangeLogService $ibLevelChangeLogService)
    {
    }

    #[PathParameter(name: 'user_code', description: '用户ID（路由参数，必填）', type: 'integer', required: true)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: "等级变更记录（分页）\n- before_level_name: 变更前等级\n- after_level_name: 变更后等级\n- before_commission_rule_name: 变更前佣金规则名称\n- after_commission_rule_name: 变更后佣金规则名称\n- trigger_type_text: 触发方式",
        type: 'array{data: array<int, array{before_level_name:string,after_level_name:string,before_commission_rule_name:string,after_commission_rule_name:string,trigger_type_text:string,changed_at:string,operator_name:string}>, total_count:int}',
        examples: [[
            'data' => [
                [
                    'before_level_name' => '黄金',
                    'after_level_name' => '白银',
                    'before_commission_rule_name' => '佣金规则A',
                    'after_commission_rule_name' => '佣金规则B',
                    'trigger_type_text' => '系统自动',
                    'changed_at' => '2026-03-26 23:55:48',
                    'operator_name' => 'admin',
                ],
            ],
            'total_count' => 1,
        ]]
    )]
    /**
     * 获取等级变更记录
     *
     * Path:
     * - user_code:int 用户ID（路由参数，必填）
     *
     * Query:
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.fields:
     * - data:array 等级变更记录列表，按变更时间倒序
     * - data[].before_level_name:string 变更前等级
     * - data[].after_level_name:string 变更后等级
     * - data[].before_commission_rule_name:string 变更前佣金规则名称
     * - data[].after_commission_rule_name:string 变更后佣金规则名称
     * - data[].trigger_type_text:string 触发方式（系统自动/人工调整）
     * - data[].changed_at:string 变更时间
     * - data[].operator_name:string 操作人
     * - total_count:int 总条数
     *
     * @param Request $request request
     * @param int $user_code 用户ID（路由参数）
     *
     * @return JsonResponse
     */
    public function index(Request $request, int $user_code): JsonResponse
    {
        $result = $this->ibLevelChangeLogService->getList([
            'user_code' => $user_code,
            'limit' => max(1, (int) $request->input('limit', 20)),
            'offset' => max(0, (int) $request->input('offset', 0)),
        ]);

        return $this->succeed($result);
    }
}
