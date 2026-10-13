<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\Api\SystemMessage;
use App\Services\Api\SystemMessageService;
use App\Services\Api\UserOperateService;
use App\Http\Requests\SystemMessage\SystemMessageRequest;
use Illuminate\Http\Request;

class SystemMessageController extends BaseController
{
    public function __construct(
        private SystemMessageService $systemMessageService,
        private UserOperateService   $userOperateService
    ) {
    }

    /**
     * 系统消息列表
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $limit  = $request->input('limit', 20); //每页条数
        $offset = $request->input('offset', 0); //偏移量

        [$data, $count] = $this->systemMessageService->getList($limit, $offset);

        return $this->succeed(['data' => $data, 'total_count' => $count]);
    }

    /**
     * 发送消息
     *
     * @param SystemMessageRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function send(SystemMessageRequest $request)
    {
        $type            = $request->input('type');
        $title           = $request->input('title', '');
        $content         = $request->input('content', '');
        $areaId          = $request->input('area_id', null);
        $user_code       = $request->input('user_code', '');

        if ($type == SystemMessage::TYPE_ASSIGN) {
            $user_code = array_filter(array_unique(explode(",", trim($user_code))));
            if (count($user_code) > 10) {
                return $this->fail(trans('user.sys_message.limit_exceeded'));
            }
            $check = $this->userOperateService->sendMsgCheckUser($areaId, $user_code);
            if (! $check) {
                return $this->fail(trans('user.message.account_error'));
            }
        }

        $this->systemMessageService->send($type, $title, $content, $areaId, $user_code);

        return $this->succeed();
    }
}
