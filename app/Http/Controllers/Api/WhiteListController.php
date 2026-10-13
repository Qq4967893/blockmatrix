<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\Api\WhiteListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\White\AddWhiteRequest;
use App\Http\Requests\White\RemoveWhiteRequest;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\QueryParameter;

class WhiteListController extends BaseController
{
    public function __construct(
        private WhiteListService $whiteListService
    ) {
    }

    /**
     * 白名单列表
     *
     * Query:
     * - user_code:int 用户ID，选填，用于精确查询
     * - limit:int 每页条数，默认 20
     * - offset:int 偏移量，默认 0
     * - register_time_start:string 注册开始时间
     * - register_time_end:string 注册结束时间
     * - white_add_time_start:string 加入白名单开始时间
     * - white_add_time_end:string 加入白名单结束时间
     *
     * Response.data[].fields:
     * - id:int 记录ID
     * - user_code:int 用户ID
     * - username:string 客户昵称
     * - type:string 账号类型文案（已转为多语言文案）0:真实,1:测试,2:黑名单
     * - email:string 邮箱
     * - register_time:string 注册时间 (Y-m-d H:i:s)
     * - operate_user_name:string 最近一次加入白名单的操作人
     * - white_add_time:string 最近一次加入白名单时间 (Y-m-d H:i:s)
     * - register_mode:string 注册方式文案
     * - is_pay:bool 是否入金（布尔）
     * - is_pay_name:string 是否入金文案
     * - wallet_address:string 钱包地址
     *
     * Response.total_count:int 数据总量
     *
     * @param Request $request request
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function list(Request $request)
    {
        /* 每页条数 */
        $limit = $request->input('limit', 20);
        /* 偏移量 */
        $offset = $request->input('offset', 0);

        return $this->succeed($this->getWhiteList($request, (int) $offset, (int) $limit));
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function export(Request $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getWhiteList($request, 0, 1);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $result = $this->getWhiteList($request, 0, (int) ($preview['total_count'] ?? 0));
        $rows = array_map(static function (array $item): array {
            return [
                'user_code' => $item['user_code'] ?? '',
                'username' => $item['username'] ?? '',
                'type' => $item['type'] ?? '',
                'email' => $item['email'] ?? '',
                'register_time' => $item['register_time'] ?? '',
                'operate_user_name' => $item['operate_user_name'] ?? '',
                'white_add_time' => $item['white_add_time'] ?? '',
                'register_mode' => $item['register_mode'] ?? '',
                'is_pay_name' => $item['is_pay_name'] ?? '',
                'wallet_address' => $item['wallet_address'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('white-list-%s', now()->format('YmdHis')),
            trans('user.export.white_list.headers'),
            $rows,
            $exportExtension
        );
    }

    private function getWhiteList(Request $request, int $offset, int $limit): array
    {
        /* 用户ID */
        $userCode = $request->input('user_code', '');
        /* 注册开始时间 */
        $registerTimeStart = $request->input('register_time_start', '');
        /* 注册结束时间 */
        $registerTimeEnd = $request->input('register_time_end', '');
        /* 加入白名单开始时间 */
        $whiteAddTimeStart = $request->input('white_add_time_start', '');
        /* 加入白名单结束时间 */
        $whiteAddTimeEnd = $request->input('white_add_time_end', '');

        [$data, $count] = $this->whiteListService->getList(
            $userCode,
            $registerTimeStart,
            $registerTimeEnd,
            $whiteAddTimeStart,
            $whiteAddTimeEnd,
            $offset,
            $limit
        );

        return ['data' => $data, 'total_count' => $count];
    }

    /**
     * 添加白名单
     *
     * Body:
     * - user_code:int 用户ID，必填
     *
     * Response:
     * - message:string 操作提示
     *
     * @param AddWhiteRequest $request request
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function add(AddWhiteRequest $request)
    {
        $userCode = $request->input('user_code');

        $result = $this->whiteListService->add($userCode);

        if (!$result['is_succ']) {
            return $this->fail($result['message']);
        }

        return $this->succeed([], $result['message']);
    }

    /**
     * 移除白名单
     *
     * Body:
     * - user_code:int 用户ID，必填
     *
     * Response:
     * - message:string 操作提示
     *
     * @param RemoveWhiteRequest $request request
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function remove(RemoveWhiteRequest $request)
    {
        $userCode = $request->input('user_code');

        $result = $this->whiteListService->remove($userCode);
        if (!$result['is_succ']) {
            return $this->fail($result['message']);
        }

        return $this->succeed([], $result['message']);
    }
}
