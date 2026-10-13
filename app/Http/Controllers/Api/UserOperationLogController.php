<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\Api\UserOperationLogService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('User Operation Log', '用户操作日志（user_operation_log）')]
class UserOperationLogController extends BaseController
{
    public function __construct(private UserOperationLogService $userOperationLogService)
    {
    }

    #[PathParameter(name: 'user_code', description: '用户ID（路由参数，必填）', type: 'integer', required: true)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: "IB变更记录（分页）\n",
        type: 'array{data: array<int, array{before:string,after:string,changed_at:string,operator_name:string}>, total_count:int}',
        examples: [[
            'data' => [
                [
                    'before' => '000001',
                    'after' => '000002',
                    'changed_at' => '2026-03-25 23:55:48',
                    'operator_name' => 'admin',
                ],
                [
                    'before' => '000002',
                    'after' => '000003',
                    'changed_at' => '2026-03-25 23:55:48',
                    'operator_name' => 'admin',
                ],
            ],
            'total_count' => 2,
        ]]
    )]
    /**
     * 获取 IB 上级代理变更记录
     *
     * Path:
     * - user_code:int 用户ID（路由参数，必填）
     *
     * Query:
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.fields:
     * - data:array 变更记录列表，按变更时间倒序
     * - data[].before:string 变更前内容；上级代理ID
     * - data[].after:string 变更后内容；上级代理ID
     * - data[].changed_at:string 变更时间
     * - data[].operator_name:string 操作人
     * - total_count:int 总条数
     *
     * @param Request $request request
     * @param int $user_code 用户ID（路由参数）
     *
     * @return JsonResponse
     */
    public function ibChangeLogs(Request $request, int $user_code): JsonResponse
    {
        $result = $this->userOperationLogService->getIbChangeLogs([
            'user_code' => $user_code,
            'limit' => max(1, (int) $request->input('limit', 20)),
            'offset' => max(0, (int) $request->input('offset', 0)),
        ]);

        return $this->succeed($result);
    }
}
