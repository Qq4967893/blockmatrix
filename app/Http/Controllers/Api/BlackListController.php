<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Black\AddBlackRequest;
use App\Http\Requests\Black\UpdateBlackPrivilegeRequest;
use App\Models\Api\User;
use App\Services\Api\BlackListService;
use App\Services\Api\UserService;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlackListController extends BaseController
{
    public function __construct(
        private BlackListService $blackListService,
        private UserService $userService
    ) {
    }

    /**
     * 获取黑名单列表
     *
     * Query:
     *  - user_code:int 用户ID
     *  - email:string 邮箱
     *  - created_start_time:string 添加黑名单开始时间
     *  - created_end_time:string 添加黑名单结束时间
     *  - reg_start_time:string 注册用户开始时间
     *  - reg_end_time:string 注册用户结束时间
     *
     * Response.data[].fields:
     * - id:int ID
     * - user_code:int 用户ID
     * - register_time:string 注册时间
     * - wallet_address:string 钱包地址
     * - email:string 邮箱
     * - operate_user_name:string 最后操作人
     * - created_at:string 添加黑名单时间
     * - reason:string 原因
     * - limits:array 限制权限
     * - has_been_cash_inflow:int 是否已入金：0未入金，1已入金
     * - reg_method:string 注册模式文案
     * - reg_method_text:string 注册模式文案
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

        return $this->succeed($this->getBlackList($request, (int) $limit, (int) $offset));
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function export(Request $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getBlackList($request, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $result = $this->getBlackList($request, (int) ($preview['total_count'] ?? 0), 0);
        $rows = array_map(static function (array $item): array {
            return [
                'user_code' => $item['user_code'] ?? '',
                'register_time' => $item['register_time'] ?? '',
                'wallet_address' => $item['wallet_address'] ?? '',
                'email' => $item['email'] ?? '',
                'operate_user_name' => $item['operate_user_name'] ?? '',
                'created_at' => $item['created_at'] ?? '',
                'reason' => $item['reason'] ?? '',
                'limits_text' => $item['limits_text'] ?? '',
                'has_been_cash_inflow_text' => $item['has_been_cash_inflow_text'] ?? '',
                'reg_method_text' => $item['reg_method_text'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('black-list-%s', now()->format('YmdHis')),
            trans('user.export.black_list.headers'),
            $rows,
            $exportExtension
        );
    }

    private function getBlackList(Request $request, int $limit, int $offset): array
    {
        /* 用户ID */
        $userCode = $request->input('user_code', '');
        /* 邮箱 */
        $email = $request->input('email', '');
        /* 添加黑名单开始时间 */
        $createdStartTime = $request->input('created_start_time', '');
        /* 添加黑名单结束时间 */
        $createdEndTime = $request->input('created_end_time', '');
        /* 注册开始时间 */
        $regStartTime = $request->input('reg_start_time', '');
        /* 注册结束时间 */
        $regEndTime = $request->input('reg_end_time', '');
        /* 排序字段 */
        $sort = $request->input('sort', 'created_at');
        /* 排序方式 */
        $order = $request->input('order', 'desc');

        [$data, $count] = $this->blackListService->getList($userCode, $email, $createdStartTime, $createdEndTime, $regStartTime, $regEndTime, $sort, $order, $limit, $offset);

        return ['data' => $data, 'total_count' => $count];
    }

    /**
     * 添加黑名单
     *
     * Query:
     * - user_code:int 用户ID，必填
     * - reason:string 原因，必填
     * - down_line:int 是否强制下线：0不强制，1强制；默认：强制下线
     * - privileges:string 用户权限, 例：1,2,3；默认全选：1,2,3,4
     *
     * @param AddBlackRequest $request request
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function add(AddBlackRequest $request)
    {
        $validated = $request->validated();

        $result = $this->blackListService->add(
            (int) $validated['user_code'],
            $validated['reason'],
            (int) $validated['down_line'],
            $validated['privileges']
        );
        if (!$result['is_succ']) {
            return $this->fail($result['message']);
        }
        return $this->succeed([], $result['message']);
    }

    /**
     * 删除黑名单
     *
     * @param int $userCode
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function delete($userCode){
        $userCodeInfo = $this->userService->getByUserCode($userCode);
        if (is_null($userCodeInfo)) {
            return $this->fail(trans('The user code does not exist.'));
        }
        if ($userCodeInfo['type'] != User::TYPE_BLACK){
            return $this->fail("The user code not added to the blacklist");
        }

        $this->blackListService->delete($userCode);
        return $this->succeed([], trans('public.message.del_success'));
    }

    /**
     * 获取黑名单权限
     *
     * Response.data[].fields:
     * - type:int 权限类型
     * - name:string 权限类型所对应的名称
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function getPrivileges(){
        $data = $this->blackListService->getPrivileges();
        return $this->succeed($data);
    }

    /**
     * 修改黑名单权限
     *
     * Query:
     * - user_code:int 用户ID，必填
     * - privilege_type:string 权限类型：1限制登录，2限制入金，3限制出金，4限制开仓
     *
     * @param UpdateBlackPrivilegeRequest $request request
     *
     * @return JsonResponse
     * @throws \Exception
     */
    public function updatePrivilege(UpdateBlackPrivilegeRequest $request){
        $validated = $request->validated();

        $result = $this->blackListService->updatePrivilege(
            (int) $validated['user_code'],
            $validated['privilege_type']
        );
        if (!$result['is_succ']) {
            return $this->fail($result['message']);
        }
        return $this->succeed([], $result['message']);
    }
}
