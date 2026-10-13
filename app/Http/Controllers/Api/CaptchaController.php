<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\EmailCaptchaLogListRequest;
use App\Http\Requests\Auth\EmailCaptchaLookupRequest;
use App\Services\Api\CaptchaLookupService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('Captcha', '验证码查询')]
class CaptchaController extends BaseController
{
    public function __construct(private CaptchaLookupService $captchaLookupService) {}

    #[QueryParameter(name: 'email', description: '用户邮箱', type: 'string', required: true)]
    #[QueryParameter(name: 'reason', description: '查询原因', type: 'string', required: true)]
    #[Response(
        description: "邮件验证码查询结果\n",
        type: 'array{code:string}',
        examples: [[
            'code' => '123456',
        ]]
    )]
    /**
     * 查询邮件验证码
     *
     * Query:
     * - email:string 用户邮箱
     * - reason:string 查询原因
     *
     * Response.fields:
     * - code:string 邮件验证码；未命中时返回空字符串
     *
     * @param EmailCaptchaLookupRequest $request request
     *
     * @return JsonResponse
     */
    public function emailCode(EmailCaptchaLookupRequest $request): JsonResponse
    {
        return $this->succeed($this->captchaLookupService->getEmailCode(
            (string) $request->input('email'),
            (string) $request->input('reason')
        ));
    }

    #[QueryParameter(name: 'email', description: '用户邮箱', type: 'string', required: false)]
    #[QueryParameter(name: 'user_code', description: '用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: "邮件验证码查询记录（分页）\n",
        type: 'array{data: array<int, array{email:string,reason:string,user_code:int,operator_name:string,operated_at:string}>, total_count:int}',
        examples: [[
            'data' => [
                [
                    'email' => 'test@example.com',
                    'reason' => 'manual lookup',
                    'user_code' => 10001,
                    'operator_name' => 'admin',
                    'operated_at' => '2026-06-04 12:00:00',
                ],
            ],
            'total_count' => 1,
        ]]
    )]
    /**
     * 查询邮件验证码记录
     *
     * Query:
     * - email:string 用户邮箱
     * - user_code:int 用户ID
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.fields:
     * - data:array 查询记录列表，按操作时间倒序
     * - data[].email:string 用户邮箱
     * - data[].reason:string 查询原因
     * - data[].user_code:int 用户ID；未匹配到用户时为 0
     * - data[].operator_name:string 操作人
     * - data[].operated_at:string 操作时间
     * - total_count:int 总条数
     *
     * @param EmailCaptchaLogListRequest $request request
     *
     * @return JsonResponse
     */
    public function emailCodeLogs(EmailCaptchaLogListRequest $request): JsonResponse
    {
        return $this->succeed($this->captchaLookupService->getEmailCodeLogs([
            'email' => $request->input('email'),
            'user_code' => $request->input('user_code') !== null ? (int) $request->input('user_code') : null,
            'limit' => max(1, (int) $request->input('limit', 20)),
            'offset' => max(0, (int) $request->input('offset', 0)),
        ]));
    }
}
