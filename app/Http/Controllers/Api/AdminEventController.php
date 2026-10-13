<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\AdminEvent\AdminEventStoreRequest;
use App\Services\Api\AdminEventService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

#[Group('Admin Event', '后台同步事件')]
class AdminEventController extends BaseController
{
    public function __construct(private AdminEventService $service) {}

    #[BodyParameter(name: 'user_code', description: '用户 ID', type: 'integer')]
    #[BodyParameter(name: 'amount', description: '赠金金额', type: 'number')]
    #[Response(description: '新增后台同步事件', type: 'array{id:int}')]
    public function openBonusFund(AdminEventStoreRequest $request): JsonResponse
    {
        $id = $this->service->createOpenBonusFund(
            (int) $request->validated('user_code'),
            (float) $request->validated('amount'),
            (int) (Auth::id() ?? 0)
        );

        return $this->succeed(['id' => $id]);
    }
}
