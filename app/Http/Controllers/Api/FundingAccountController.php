<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\Api\FundingAccountService;
use App\Services\Api\FundingLedgerService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('Funds', '资金账户')]
class FundingAccountController extends BaseController
{
    public function __construct(
        private FundingAccountService $service,
        private FundingLedgerService $fundingLedgerService,
    ){}

    #[PathParameter(name: 'user_code', description: '用户ID（路由参数，必填）', type: 'integer', required: true)]
    #[Response(
        description: '资金账户信息',
        type: 'array{data: array<int, array{funding_account_id:int,total_balance:float}>}'
    )]
    /**
     * 获取用户资金账户信息
     *
     * @param int $userCode 用户ID
     *
     * @return JsonResponse
     */
    public function show(int $userCode): JsonResponse
    {
        return $this->succeed($this->service->getByUserCode($userCode));
    }

    #[PathParameter(name: 'user_code', description: '用户ID（路由参数，必填）', type: 'integer', required: true)]
    #[Response(
        description: '账户赠金信息',
        type: 'array{data: array{in:string,out:string,net:string}}'
    )]
    /**
     * 获取账户赠金信息
     *
     * @param int $userCode 用户ID
     *
     * @return JsonResponse
     */
    public function bonus(int $userCode): JsonResponse
    {
        $result = $this->fundingLedgerService->getBonusByUserCode($userCode);

        return $this->succeed($result);
    }
}
