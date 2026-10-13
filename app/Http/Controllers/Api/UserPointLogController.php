<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Point\UserPointLogListRequest;
use App\Services\Api\UserPointLogService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;

#[Group('Points', '积分明细')]
class UserPointLogController extends BaseController
{
    public function __construct(private UserPointLogService $service)
    {
    }

    #[QueryParameter(name: 'user_code', description: '筛选指定用户ID，不传则查询所有', type: 'integer', required: false)]
    #[QueryParameter(name: 'start_at', description: '开始时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'end_at', description: '结束时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: '积分明细列表',
        type: 'array{data: array<int, array{id:int,user_code:int,source:string,event_key:?string,remark:?string,points:int,points_display:string,created_at:string}>, total_count:int}',
        examples: [[
            'data' => [
                [
                    'id' => 1,
                    'user_code' => 10000001,
                    'source' => '新手任务-首次注册',
                    'event_key' => 'registered',
                    'remark' => '注册奖励',
                    'points' => 100,
                    'points_display' => '+100 PTS',
                    'created_at' => '2025-03-01 10:00:00',
                ],
            ],
            'total_count' => 1,
        ]]
    )]
    /**
     * 获取积分明细列表
     *
     * @param UserPointLogListRequest $request request
     * @param int|null $userCode 用户ID
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(UserPointLogListRequest $request, ?int $userCode = null)
    {
        $userCode = $userCode ?: $request->input('user_code');
        $startAt  = $request->input('start_at');
        $endAt    = $request->input('end_at');
        $limit    = (int) $request->input('limit', 20);
        $offset   = (int) $request->input('offset', 0);

        $data = $this->service->getList(
            $userCode ? (int) $userCode : null,
            $startAt,
            $endAt,
            $limit,
            $offset
        );

        return $this->succeed($data);
    }
}
