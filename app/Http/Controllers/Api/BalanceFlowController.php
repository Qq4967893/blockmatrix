<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\BalanceFlow\BalanceFlowListRequest;
use App\Services\Api\BalanceFlowService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('Funds', '资金流水')]
class BalanceFlowController extends BaseController
{
    public function __construct(private BalanceFlowService $balanceFlowService)
    {
    }

    #[Response(
        description: '子账号资金类型列表',
        type: 'array<int, array{code:int,name:string,sub_types?:array<int,array{code:int,name:string}>}>'
    )]
    /**
     * 获取子账号资金类型
     *
     * @return JsonResponse
     */
    public function types(): JsonResponse
    {
        return $this->succeed($this->balanceFlowService->getTypeOptions());
    }

    #[QueryParameter(name: 'trade_ticket', description: '交易单号', type: 'string', required: false)]
    #[QueryParameter(name: 'created_start', description: '生成开始时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'created_end', description: '生成结束时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'fund_type', description: '资金类型 code', type: 'integer', required: false)]
    #[QueryParameter(name: 'fund_sub_type', description: '资金子类型 code；目前支持 17=信用赠金解锁', type: 'integer', required: false)]
    #[QueryParameter(name: 'login', description: '交易账号', type: 'string', required: false)]
    #[QueryParameter(name: 'account_type', description: '账号类型：0统一交易 1跟单 2比赛 3赠金', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: '子账号资金记录列表',
        type: 'array{data: array<int, array{id:int,trade_ticket:string,login:int,account_type:int,account_type_text:string,fund_type:int,fund_sub_type:int,fund_type_text:string,created_at:?string,amount:float,balance:float,bonus_balance:float}>, total_count:int}'
    )]
    /**
     * 获取子账号资金流水列表
     *
     * @param BalanceFlowListRequest $request request
     * @param int $user_code 用户ID
     *
     * @return JsonResponse
     */
    public function index(BalanceFlowListRequest $request, int $user_code): JsonResponse
    {
        $result = $this->balanceFlowService->getList(
            $user_code,
            [
                'trade_ticket'  => $request->input('trade_ticket'),
                'created_start' => $request->input('created_start'),
                'created_end'   => $request->input('created_end'),
                'fund_type'     => $request->input('fund_type'),
                'fund_sub_type' => $request->input('fund_sub_type'),
                'login'         => $request->input('login'),
                'account_type'  => $request->input('account_type'),
            ],
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }
}
