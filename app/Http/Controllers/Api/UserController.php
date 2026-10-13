<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\User\UserTransferCheckRequest;
use App\Http\Requests\User\UserTransferRequest;
use App\Models\Api\User;
use App\Services\Api\UserService;
use App\Services\Api\IbAccountService;
use App\Services\Api\LoginLogService;
use App\Services\Blockmatrix\UserOperationLogService;
use App\Models\Blockmatrix\UserOperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;

class UserController extends BaseController
{
    public function __construct(
        private UserService $userService,
        private IbAccountService $ibAccountService,
        private LoginLogService $loginLogService,
        private UserOperationLogService $userOperationLogService,
    ) {
    }

    /**
     * 客户列表
     *
     * Query:
     * - anyway:string 关键字，支持按用户ID、邮箱、用户名模糊搜索
     * - pay_status:int 是否充值：0全部，1已充值，2未充值
     * - ib_affiliation:int 代理归属
     * - register_time_start:string 注册开始时间
     * - register_time_end:string 注册结束时间
     * - sort:string 排序字段
     * - order:string 排序方式：asc/desc
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - user_code:int 用户ID
     * - username:string 用户名
     * - email:string 邮箱
     * - type:int 账号类型（0:真实,1:测试,2:黑名单）
     * - type_text:string 账号类型文案（多语言）
     * - ib_affiliation:?int 代理归属
     * - reg_way:string 注册方式
     * - network:string 网络（根据用户最新 chain_id 映射，如 TRON/ETH）
     * - is_pay:bool 是否已充值
     * - is_pay_name:string 是否已充值文案
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(Request $request)
    {
        /* 每页条数 */
        $limit = $request->input('limit', 20);
        /* 偏移量 */
        $offset = $request->input('offset', 0);
        $data = $this->getUserList($request, (int) $limit, (int) $offset);

        return $this->succeed($data);
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function export(Request $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getUserList($request, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $exportCount = (int) ($preview['total_count'] ?? 0);
        $result = $this->getUserList($request, $exportCount, 0);

        $rows = array_map(static function (array $item): array {
            return [
                'user_code' => $item['user_code'] ?? '',
                'username' => $item['username'] ?? '',
                'email' => $item['email'] ?? '',
                'type_text' => $item['type_text'] ?? '',
                'ib_affiliation' => $item['ib_affiliation'] ?? '',
                'reg_way' => $item['reg_way'] ?? '',
                'network' => $item['network'] ?? '',
                'wallet_address' => $item['wallet_address'] ?? '',
                'is_pay_name' => $item['is_pay_name'] ?? '',
                'created_at' => $item['created_at'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('users-%s', now()->format('YmdHis')),
            trans('user.export.headers'),
            $rows,
            $exportExtension
        );
    }

    private function getUserList(Request $request, int $limit, int $offset): array
    {
        return $this->userService->getList(
            /*关键字 */
            $request->input('anyway'),
            /* 是否充值 0-全部 1-是 2-否 */
            (int) $request->input('pay_status', User::PAY_STATUS_ALL),
            /* 代理归属 */
            $request->input('ib_affiliation'),
            /* 注册开始时间 */
            $request->input('register_time_start', ''),
            /* 注册结束时间 */
            $request->input('register_time_end', ''),
            /* 排序字段 */
            $request->input('sort', 'id'),
            /* 排序方式 */
            $request->input('order', 'desc'),
            $limit,
            $offset
        );
    }

    /**
     * 用户下线
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function downLine(Request $request)
    {
        $userCode = $request->input('user_code');

        $user = $this->userService->getByUserCode($userCode);
        if (empty($user)) {
            return $this->fail(trans('user.message.not_exist'));
        }

        $result = $this->userService->downLine($userCode);

        return $this->succeed($result);
    }

    /**
     * 客户划转
     *
     * 说明:
     * - 先校验目标用户是否为代理商
     * - 若当前用户本身也是代理商，则同步调整其代理层级链
     * - 无论是否为代理商，都会更新用户的 ib_affiliation 并记录 user_operation_log
     *
     * Body:
     * - user_code:int 用户ID，必填
     * - target_user_code:int 目标代理用户ID，必填
     *
     * @param UserTransferRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function transfer(UserTransferRequest $request)
    {
        $userCode = (int) $request->input('user_code');
        $targetUserCode = (int) $request->input('target_user_code');

        $userOperationLog = ['parent_before' => ['ib_id' => null, 'user_code' => null], 'parent_after' => ['ib_id' => null, 'user_code' => $targetUserCode]];

        // 判断target_user_code是否是代理商
        $targetUserCodeIb = $this->ibAccountService->getInfo($targetUserCode);
        if (! $targetUserCodeIb) {
            return $this->fail(trans('user.message.transfer_is_not_allowed.1'));
        }
        $userOperationLog['parent_after']['ib_id'] = $targetUserCodeIb->id;

        // 判断是代理商还是客户，如果是代理商去修改代理商链和客户归属，如果是客户直接修改客户归属即可
        $userCodeIb = $this->ibAccountService->getInfo($userCode);
        if ($userCodeIb){
            $userOperationLog['parent_before']['ib_id'] = $userCodeIb->parent_id;
            $userOperationLog['parent_before']['user_code'] = $userCodeIb->parent_user_code;
        }

        $apiConnection = DB::connection(DB_API);

        try {
            $apiConnection->beginTransaction();

            if ($userCodeIb){
                $ibAccountResult = $this->ibAccountService->transfer($userCode, $targetUserCode);
                if (! $ibAccountResult['is_succ']) {
                    $apiConnection->rollBack();

                    return $this->fail($ibAccountResult['message']);
                }
            }

            $userResult = $this->userService->transfer($userCode, $targetUserCode);
            if (! $userResult['is_succ']) {
                $apiConnection->rollBack();

                return $this->fail($userResult['message']);
            }

            // 记录划转客户log
            $this->userOperationLogService->addRecord(UserOperationLog::DEFAULT_TARGET_ID, UserOperationLog::TARGET_TYPE_IB_PARENT, json_encode($userOperationLog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), UserOperationLog::ACTION_TYPE_UPDATE, $userCode);

            $apiConnection->commit();
        } catch (\Throwable $throwable) {
            $apiConnection->rollBack();

            throw $throwable;
        }

        return $this->succeed();
    }

    /**
     * 客户划转校验
     *
     * 说明:
     * - 当传入 target_user_code 时，会先校验目标用户是否为代理商
     * - 若当前 user_code 对应的是代理商，还会继续校验代理链路是否允许划转
     * - 全部校验通过后，返回当前客户基础信息供前端确认
     *
     * Query:
     * - user_code:int 用户ID，必填
     * - target_user_code:int 目标客户用户ID，选填（验证目标上级代理时填写）
     *
     * Response.fields:
     * - user_code:int 用户ID
     * - username:string 客户姓名
     * - ib_affiliation:int 当前上级代理ID
     *
     * Error:
     * - 422 参数校验失败：user_code 缺失、不是整数、或小于 1
     *
     * @param UserTransferCheckRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function options(UserTransferCheckRequest $request)
    {
        $userCode = $request->input('user_code');
        $targetUserCode = $request->input('target_user_code');

        // 如果target_user_code是null，是验证客户用户ID；如果传递了，是验证目标客户用户ID
        if (! is_null($targetUserCode)) {
            if (! $this->ibAccountService->isIb((int) $targetUserCode)){
                return $this->fail(trans('user.message.transfer_is_not_allowed.1'));
            }
        }

        if ($this->ibAccountService->isIb((int) $userCode)) {
            $ibAccountResult = $this->ibAccountService->checkTransferUser($userCode, $targetUserCode);
            if (! $ibAccountResult['is_succ']) {
                return $this->fail($ibAccountResult['message']);
            }
        }

        $result = $this->userService->checkTransferUser($userCode);
        if (! $result['is_succ']) {
            return $this->fail($result['message']);
        }

        return $this->succeed($result['data']);
    }

    #[QueryParameter(name: 'keywords', description: '关键字；配合 keyword_type 查询', type: 'string', required: false)]
    #[QueryParameter(name: 'keyword_type', description: '关键字类型：all、request_id、user_code、ip、user_agent', type: 'string', required: false, default: 'all')]
    #[QueryParameter(name: 'is_latest', description: '是否最新一次登录：1是，0否', type: 'integer', required: false)]
    #[QueryParameter(name: 'country', description: '国家二字母编码，不区分大小写', type: 'string', required: false)]
    #[QueryParameter(name: 'login_type', description: '登录方式，值取自 user/login_logs/options 的 login_types[].key', type: 'string', required: false)]
    #[QueryParameter(name: 'login_time_start', description: '登录开始时间，格式 Y-m-d H:i:s', type: 'string', required: false)]
    #[QueryParameter(name: 'login_time_end', description: '登录结束时间，格式 Y-m-d H:i:s', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数，最小值为 1', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量，负数按 0 处理', type: 'integer', required: false, default: 0)]
    #[Response(
        description: '用户登录日志列表',
        type: 'array{data: array<int, array{id:int,request_id:string,user_code:int,email:string,is_latest:int,is_latest_text:string,ip:string,country:string,country_text:string,user_agent:string,device_uuid:string,timezone:string,canvas_hash:string,audio_hash:string,screen_resolution:string,device_hash:string,login_type:string,login_type_text:string,login_time:string}>, total_count:int}'
    )]
    /**
     * 用户登录日志列表
     *
     * Query:
     * - keywords:string 关键字
     * - keyword_type:string 关键字类型：all/request_id/user_code/ip/user_agent
     * - is_latest:int 是否最新一次登录：1是，0否
     * - country:string 国家编码
     * - login_type:string 登录方式，取 user/login_logs/options.login_types[].key
     * - login_time_start:string 登录开始时间
     * - login_time_end:string 登录结束时间
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - id:int 数据ID
     * - request_id:string 请求ID
     * - user_code:int 用户ID
     * - email:string 账号邮箱
     * - is_latest:int 是否最新一次登录：1是，0否
     * - is_latest_text:string 是否最新一次登录文案
     * - ip:string IP
     * - country:string 国家编码
     * - country_text:string 国家名称
     * - user_agent:string User Agent
     * - login_type:string 登录方式
     * - login_type_text:string 登录方式文案
     * - login_time:string 登录时间
     *
     * @param Request $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginLogs(Request $request)
    {
        $result = $this->getLoginLogList(
            $request,
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function exportLoginLogs(Request $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getLoginLogList($request, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $exportCount = (int) ($preview['total_count'] ?? 0);
        $result = $this->getLoginLogList($request, $exportCount, 0);

        $rows = array_map(static function (array $item): array {
            return [
                'id' => $item['id'] ?? '',
                'request_id' => $item['request_id'] ?? '',
                'user_code' => $item['user_code'] ?? '',
                'is_latest_text' => $item['is_latest_text'] ?? '',
                'ip' => $item['ip'] ?? '',
                'country_text' => $item['country_text'] ?? '',
                'user_agent' => $item['user_agent'] ?? '',
                'device_uuid' => $item['device_uuid'] ?? '',
                'timezone' => $item['timezone'] ?? '',
                'canvas_hash' => $item['canvas_hash'] ?? '',
                'audio_hash' => $item['audio_hash'] ?? '',
                'screen_resolution' => $item['screen_resolution'] ?? '',
                'device_hash' => $item['device_hash'] ?? '',
                'login_type_text' => $item['login_type_text'] ?? '',
                'login_time' => $item['login_time'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('user-login-logs-%s', now()->format('YmdHis')),
            trans('user.export.login_logs.headers'),
            $rows,
            $exportExtension
        );
    }

    private function getLoginLogList(Request $request, int $limit, int $offset): array
    {
        return $this->loginLogService->getList(
            [
                'keywords' => $request->input('keywords'),
                'keyword_type' => $request->input('keyword_type', 'all'),
                'is_latest' => $request->input('is_latest'),
                'country' => $request->input('country'),
                'login_type' => $request->input('login_type'),
                'login_time_start' => $request->input('login_time_start'),
                'login_time_end' => $request->input('login_time_end'),
            ],
            $limit,
            $offset
        );
    }

    /**
     * 用户登录日志筛选项
     *
     * Response.fields:
     * - login_types:array 登录方式列表（key/value）
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginLogOptions()
    {
        return $this->succeed($this->loginLogService->options());
    }
}
