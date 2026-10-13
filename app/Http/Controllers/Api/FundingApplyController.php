<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\FundingApply\FundingApplyListRequest;
use App\Http\Requests\FundingApply\FundingApplyFinanceAuditRequest;
use App\Http\Requests\FundingApply\FundingApplyFinanceRejectRequest;
use App\Http\Requests\FundingApply\FundingApplyRiskApproveRequest;
use App\Http\Requests\FundingApply\FundingApplyRiskAuditRequest;
use App\Http\Requests\FundingApply\FundingApplyRiskRejectRequest;
use App\Http\Requests\FundingApply\FundingApplyWithdrawalFinanceListRequest;
use App\Http\Requests\FundingApply\FundingApplyWithdrawalRiskListRequest;
use App\Models\Api\FundingApply;
use App\Services\Api\BlackListService;
use App\Services\Api\FundingApplyService;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Scaffold\Funding\Enums\FundingApplyStatus;
use Scaffold\Funding\Enums\FundingFinanceStatus;
use Scaffold\Funding\Enums\FundingRiskStatus;

#[Group('Funds', '入金列表')]
class FundingApplyController extends BaseController
{
    public function __construct(
        private FundingApplyService $fundingApplyService,
        private BlackListService $blackListService,
    ){}

    #[Response(
        description: '入金筛选项，platforms 与 funding_network_configs/options.channels 一致，包含充值类型、状态与入金状态，deposit_status 支持首次/多次入金筛选',
        type: 'array{deposit_types: array<int, array{code:int,name:string}>, platforms: array<int, array{code:string,name:string}>, statuses: array<int, array{code:int,name:string}>, deposit_statuses: array<int, array{code:string,name:string}>}',
        examples: [[
            'deposit_types' => [
                ['code' => 1, 'name' => '入金'],
            ],
            'platforms' => [
                ['code' => 'cobo', 'name' => 'cobo'],
                ['code' => 'cregis', 'name' => 'cregis'],
            ],
            'statuses' => [
                ['code' => 0, 'name' => '待处理'],
                ['code' => 4, 'name' => '已通过'],
                ['code' => 5, 'name' => '异常'],
            ],
            'deposit_statuses' => [
                ['code' => 'first_time', 'name' => '首次入金'],
                ['code' => 'multiple', 'name' => '多次入金'],
            ],
        ]]
    )]
    /**
     * 获取入金筛选项
     *
     * @return JsonResponse
     */
    public function types(): JsonResponse
    {
        return $this->succeed($this->fundingApplyService->getFilterOptions());
    }

    #[QueryParameter(name: 'user_code', description: '用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'status', description: '状态：0=待处理（user_code≠0），4=已通过，5=异常（按 user_code=0 筛选）', type: 'integer', required: false)]
    #[QueryParameter(name: 'deposit_status', description: '入金状态：first_time=首次入金，multiple=多次入金', type: 'string', required: false)]
    #[QueryParameter(name: 'received_time_start', description: '入金开始时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'received_time_end', description: '入金结束时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'direction', description: '方向：1=入，-1=出', type: 'integer', required: false)]
    #[QueryParameter(name: 'platform', description: '通道编码，取 deposits/filters.platforms[].code', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    /**
     * 获取入金列表
     *
     * Query:
     * - user_code:int 用户ID
     * - status:int 状态
     * - deposit_status:string 入金状态（first_time/multiple）
     * - received_time_start:string 入金开始时间
     * - received_time_end:string 入金结束时间
     * - direction:int 方向（1入，-1出）
     * - platform:string 通道编码
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - network:string 网络编码
     * - network_text:string 网络展示文案，如 `以太坊 Sepolia 测试网(ERC20)`
     * @param FundingApplyListRequest $request request
     *
     * @return JsonResponse
     */
    public function index(FundingApplyListRequest $request): JsonResponse
    {
        $result = $this->getDepositList(
            $request,
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function export(FundingApplyListRequest $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getDepositList($request, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $exportCount = (int) ($preview['total_count'] ?? 0);
        $result = $this->getDepositList($request, $exportCount, 0);

        $rows = array_map(static function (array $item): array {
            return [
                'user_code' => $item['user_code'] ?? '',
                'from' => $item['from'] ?? '',
                'to' => $item['to'] ?? '',
                'order_no' => $item['order_no'] ?? '',
                'third_no' => $item['third_no'] ?? '',
                'deposit_type_text' => $item['deposit_type_text'] ?? '',
                'channel_text' => $item['channel_text'] ?? '',
                'currency' => $item['currency'] ?? '',
                'apply_amount' => $item['apply_amount'] ?? '',
                'pay_currency' => $item['pay_currency'] ?? '',
                'pay_amount' => $item['pay_amount'] ?? '',
                'rate' => $item['rate'] ?? '',
                'received_at' => $item['received_at'] ?? '',
                'tx_hash' => $item['tx_hash'] ?? '',
                'network' => ($item['network_text'] ?? '') !== '' ? $item['network_text'] : ($item['network'] ?? ''),
                'email' => $item['email'] ?? '',
                'deposit_status_text' => $item['deposit_status_text'] ?? '',
                'status_text' => $item['status_text'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('deposits-%s', now()->format('YmdHis')),
            trans('deposit.export.title'),
            $rows,
            $exportExtension
        );
    }

    #[Response(
        description: '出金风控/财务筛选项，platforms 与 funding_network_configs/options.channels 一致',
        type: 'array{platforms: array<int, array{code:string,name:string}>, risk_statuses: array<int, array{code:string,name:string}>, finance_statuses: array<int, array{code:string,name:string}>}',
        examples: [[
            'platforms' => [
                ['code' => 'cobo', 'name' => 'cobo'],
                ['code' => 'cregis', 'name' => 'cregis'],
            ],
            'risk_statuses' => [
                ['code' => 'pending', 'name' => '待审核'],
                ['code' => 'approved', 'name' => '已通过'],
                ['code' => 'rejected', 'name' => '已驳回'],
            ],
            'finance_statuses' => [
                ['code' => 'pending', 'name' => '待处理'],
                ['code' => 'approved', 'name' => '已通过'],
                ['code' => 'failed', 'name' => '失败'],
                ['code' => 'rejected', 'name' => '已驳回'],
            ],
        ]]
    )]
    /**
     * 获取 funding_apply 出金筛选项
     *
     * @return JsonResponse
     */
    public function withdrawalOptions(): JsonResponse
    {
        return $this->succeed($this->fundingApplyService->getWithdrawalOptions());
    }

    #[QueryParameter(name: 'user_code', description: '用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'order_no', description: '订单号', type: 'integer', required: false)]
    #[QueryParameter(name: 'withdraw_time_start', description: '出金开始时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'withdraw_time_end', description: '出金结束时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'platform', description: '通道编码，取 withdrawals/options.platforms[].code', type: 'string', required: false)]
    #[QueryParameter(name: 'status', description: '风控状态：pending|approved|rejected', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    /**
     * 获取 funding_apply 出金风控列表
     *
     * Response.data[].fields:
     * - type:int 客户类型（0:真实,1:测试,2:黑名单）
     * - type_text:string 客户类型文案（普通客户显示空字符串）
     * - network:string 网络编码
     * - network_text:string 网络展示文案，如 `以太坊 Sepolia 测试网(ERC20)`
     * - operator_name:string 操作人
     * - risk_reason:string 风控原因/驳回原因
     * - daily_withdraw_count:int 当前订单所属 UTC 自然日的全部成功提现次数 + 当前订单（不重复）
     * - daily_total_withdraw_amount:number 当前订单所属 UTC 自然日的全部成功提现金额 + 当前订单申请金额（不重复）
     *
     * @param FundingApplyWithdrawalRiskListRequest $request request
     *
     * @return JsonResponse
     */
    public function riskApply(FundingApplyWithdrawalRiskListRequest $request): JsonResponse
    {
        $result = $this->getWithdrawalRiskList(
            $request,
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function exportRisk(FundingApplyWithdrawalRiskListRequest $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getWithdrawalRiskList($request, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $exportCount = (int) ($preview['total_count'] ?? 0);
        $result = $this->getWithdrawalRiskList($request, $exportCount, 0);

        $rows = array_map(static function (array $item): array {
            return [
                'user_code' => $item['user_code'] ?? '',
                'from' => $item['from'] ?? '',
                'to' => $item['to'] ?? '',
                'order_no' => $item['order_no'] ?? '',
                'third_no' => $item['third_no'] ?? '',
                'channel_text' => $item['channel_text'] ?? '',
                'currency' => $item['currency'] ?? '',
                'apply_amount' => $item['apply_amount'] ?? '',
                'daily_withdraw_count' => $item['daily_withdraw_count'] ?? 0,
                'daily_total_withdraw_amount' => $item['daily_total_withdraw_amount'] ?? 0,
                'actual_currency' => $item['actual_currency'] ?? '',
                'actual_amount' => $item['actual_amount'] ?? '',
                'fee_amount' => $item['fee_amount'] ?? '',
                'rate' => $item['rate'] ?? '',
                'withdrawal_time' => $item['withdrawal_time'] ?? '',
                'arrival_time' => $item['arrival_time'] ?? '',
                'tx_hash' => $item['tx_hash'] ?? '',
                'network' => ($item['network_text'] ?? '') !== '' ? $item['network_text'] : ($item['network'] ?? ''),
                'email' => $item['email'] ?? '',
                'type_text' => $item['type_text'] ?? '',
                'risk_status_text' => $item['risk_status_text'] ?? '',
                'operator_name' => $item['operator_name'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('withdrawals-risk-%s', now()->format('YmdHis')),
            trans('withdrawal.export.title_risk'),
            $rows,
            $exportExtension
        );
    }

    #[QueryParameter(name: 'user_code', description: '用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'order_no', description: '订单号', type: 'integer', required: false)]
    #[QueryParameter(name: 'withdraw_time_start', description: '出金开始时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'withdraw_time_end', description: '出金结束时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'platform', description: '通道编码，取 withdrawals/options.platforms[].code', type: 'string', required: false)]
    #[QueryParameter(name: 'status', description: '财务状态：pending|approved|failed|rejected', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: "出金财务列表（分页）。data[].retry 是 reference_content.wallet_dispatch 的 API 投影，用于核对钱包重试状态：\n- request_id: Walletd 幂等键，格式 {order_no}@retry，一单终身复用\n- retryable: 是否为可重试错误；仅 last_error_code 严格等于 WITHDRAWAL_NOT_SUBMITTED 时为 true\n- retry_phase: none=无重试记录/存量单，retrying=处理中并等待自动重试，submitted=钱包已受理，exhausted=自动重试耗尽且已置财务失败，cancelled=人工通过/驳回等操作已接管\n- auto_retry_count: 已消耗的自动重试次数；手动重试不增加\n- auto_max_attempts: 自动重试上限，当前固定 3\n- next_retry_at: 下次自动重试时间；手动重试可提前于该时间点击\n- retry_in_progress: 上一次钱包请求未返回时为 true；此时再次点击重试会返回“钱包出金重试正在处理中”\n- last_error_code: 最近一次钱包错误码；只有严格等于 WITHDRAWAL_NOT_SUBMITTED 才允许重试\n- last_error_message: 最近一次钱包错误描述\n- can_manual_retry: 是否显示重试按钮；必须 can_handle_finance=1、retryable=true、retry_in_progress=false，且 retry_phase=retrying/exhausted\n\n后端持久化的 wallet_dispatch 另有 retry_lease_until（并发租约，接口不直接返回，投影为 retry_in_progress）与 last_attempted_at（最近尝试时间，接口不返回）。出金限制用户的 can_manual_retry 仍可能为 1，但调用重试接口返回 code=102001。",
        type: 'array{data: array<int, array{id:int,user_code:int,from:string,to:string,order_no:string,third_no:string,deposit_type:int,deposit_type_text:string,platform:string,platform_text:string,channel_text:string,currency:string,apply_amount:float,fee_amount:float,actual_currency:?string,actual_amount:float,pay_currency:?string,pay_amount:?float,rate:?float,received_at:?string,arrival_time:?string,tx_hash:string,network:string,email:string,type:int,type_text:string,operator_name:string,is_white_list_customer:bool,can_fail_finance:int,can_handle_finance:int,deposit_status_text:string,status:array{code:int,text:string,risk_status:int,finance_status:int},status_text:string,risk_status_text:string,finance_status_text:string,risk_reason:string,finance_reason:string,withdrawal_time:?string,retry:array{request_id:string,retryable:bool,retry_phase:string,auto_retry_count:int,auto_max_attempts:int,next_retry_at:?string,retry_in_progress:bool,last_error_code:?string,last_error_message:?string},can_manual_retry:int}>, total_count:int}',
        examples: [[
            'data' => [
                [
                    'order_no' => '1001',
                    'status' => [
                        'code' => FundingApplyStatus::Processing->value,
                        'risk_status' => FundingRiskStatus::Approved->value,
                        'finance_status' => FundingFinanceStatus::Pending->value,
                    ],
                    'can_handle_finance' => 1,
                    'retry' => [
                        'request_id' => '1001@retry',
                        'retryable' => true,
                        'retry_phase' => 'retrying',
                        'auto_retry_count' => 1,
                        'auto_max_attempts' => 3,
                        'next_retry_at' => '2026-09-08 12:06:00',
                        'retry_in_progress' => false,
                        'last_error_code' => 'WITHDRAWAL_NOT_SUBMITTED',
                        'last_error_message' => 'withdrawal was not submitted to PSP',
                    ],
                    'can_manual_retry' => 1,
                ],
            ],
            'total_count' => 1,
        ]]
    )]
    /**
     * 获取 funding_apply 出金财务列表
     *
     * Response.data[].fields:
     * - type:int 客户类型（0:真实,1:测试,2:黑名单）
     * - type_text:string 客户类型文案（普通客户显示空字符串）
     * - network:string 网络编码
     * - network_text:string 网络展示文案，如 `以太坊 Sepolia 测试网(ERC20)`
     * - operator_name:string 操作人（固定显示系统）
     * - finance_reason:string 财务原因/驳回原因
     * - can_fail_finance:int 是否允许财务处理驳回（1=允许，0=不允许）；status=3/risk_status=1/finance_status=1 或 status=5/risk_status=1/finance_status=3 时为 1
     * - can_handle_finance:int 是否允许财务处理通过（1=允许，0=不允许）；逻辑同 can_fail_finance
     * - retry:object reference_content.wallet_dispatch 的 API 投影
     * - retry.request_id:string Walletd 幂等键，格式 {order_no}@retry，一单终身复用
     * - retry.retryable:bool 仅 last_error_code 严格等于 WITHDRAWAL_NOT_SUBMITTED 时为 true
     * - retry.retry_phase:string none=无重试记录/存量单，retrying=处理中并等待自动重试，submitted=钱包已受理，exhausted=自动重试耗尽且已置财务失败，cancelled=人工通过/驳回等操作已接管
     * - retry.auto_retry_count:int 已消耗的自动重试次数；手动重试不增加
     * - retry.auto_max_attempts:int 自动重试上限，当前固定 3
     * - retry.next_retry_at:?string 下次自动重试时间；手动重试可提前于该时间点击
     * - retry.retry_in_progress:bool 上一次钱包请求未返回时为 true
     * - retry.last_error_code:?string 最近一次钱包错误码；只有严格等于 WITHDRAWAL_NOT_SUBMITTED 才允许重试
     * - retry.last_error_message:?string 最近一次钱包错误描述
     * - can_manual_retry:int 是否显示“重新发起钱包出金”按钮（1=显示，0=隐藏）；订单仍可人工处理、retry_phase=retrying/exhausted、last_error_code=WITHDRAWAL_NOT_SUBMITTED，且当前无重试请求处理中时为 1；出金限制用户仍会返回 1，但调用重试接口返回 code=102001
     *
     * @param FundingApplyWithdrawalFinanceListRequest $request request
     *
     * @return JsonResponse
     */
    public function financeApply(FundingApplyWithdrawalFinanceListRequest $request): JsonResponse
    {
        $result = $this->getWithdrawalFinanceList(
            $request,
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function exportFinance(FundingApplyWithdrawalFinanceListRequest $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getWithdrawalFinanceList($request, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $exportCount = (int) ($preview['total_count'] ?? 0);
        $result = $this->getWithdrawalFinanceList($request, $exportCount, 0);

        $rows = array_map(static function (array $item): array {
            return [
                'user_code' => $item['user_code'] ?? '',
                'from' => $item['from'] ?? '',
                'to' => $item['to'] ?? '',
                'order_no' => $item['order_no'] ?? '',
                'third_no' => $item['third_no'] ?? '',
                'channel_text' => $item['channel_text'] ?? '',
                'currency' => $item['currency'] ?? '',
                'apply_amount' => $item['apply_amount'] ?? '',
                'actual_currency' => $item['actual_currency'] ?? '',
                'actual_amount' => $item['actual_amount'] ?? '',
                'fee_amount' => $item['fee_amount'] ?? '',
                'rate' => $item['rate'] ?? '',
                'withdrawal_time' => $item['withdrawal_time'] ?? '',
                'tx_hash' => $item['tx_hash'] ?? '',
                'network' => ($item['network_text'] ?? '') !== '' ? $item['network_text'] : ($item['network'] ?? ''),
                'email' => $item['email'] ?? '',
                'type_text' => $item['type_text'] ?? '',
                'finance_status_text' => $item['finance_status_text'] ?? '',
                'operator_name' => $item['operator_name'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('withdrawals-finance-%s', now()->format('YmdHis')),
            trans('withdrawal.export.title_finance'),
            $rows,
            $exportExtension
        );
    }

    #[BodyParameter(name: 'id', description: '出金订单号（funding_apply.order_no）', type: 'integer')]
    #[BodyParameter(name: 'result', description: '审核结果：approved|rejected', type: 'string')]
    #[BodyParameter(name: 'reason', description: '风控驳回原因（result=rejected 时必填）', type: 'string', required: false)]
    /**
     * funding_apply 出金风控审核
     *
     * @param FundingApplyRiskAuditRequest $request request
     *
     * @return JsonResponse
     */
    public function riskAudit(FundingApplyRiskAuditRequest $request): JsonResponse
    {
        $orderNo = $this->fundingApplyService->resolveWithdrawalOrderNoByIdOrFail((int) $request->input('id'));
        $result = (string) $request->input('result');

        if ($result === FundingApply::RISK_RESULT_APPROVED) {
            $auditMeta = $this->fundingApplyService->getWithdrawalAuditMeta($orderNo);
            $hasWithdrawalRestriction = $this->blackListService->hasWithdrawalRestriction((int) ($auditMeta['user_code'] ?? 0));
            if ($hasWithdrawalRestriction) {
                return $this->succeed([], trans('withdrawal.message.has_withdrawal_restriction'), 102001);
            }

            $auditResult = $this->fundingApplyService->approveRisk(
                $orderNo,
                Auth::id() ?? 0
            );

            return $this->succeed($auditResult);
        }

        $auditResult = $this->fundingApplyService->rejectRisk(
            $orderNo,
            Auth::id() ?? 0,
            (string) $request->input('reason')
        );

        return $this->succeed($auditResult);
    }

    #[BodyParameter(name: 'order_no', description: '出金订单号（funding_apply.order_no）', type: 'integer')]
    /**
     * 审核通过 funding_apply 出金风控
     *
     * @param FundingApplyRiskApproveRequest $request request
     *
     * @return JsonResponse
     */
    public function riskApprove(FundingApplyRiskApproveRequest $request): JsonResponse
    {
        try {
            $orderNo = (int) $request->input('order_no');
            $auditMeta = $this->fundingApplyService->getWithdrawalAuditMeta($orderNo);
            $hasWithdrawalRestriction = $this->blackListService->hasWithdrawalRestriction((int) ($auditMeta['user_code'] ?? 0));
            if ($hasWithdrawalRestriction) {
                return $this->succeed([], trans('withdrawal.message.has_withdrawal_restriction'), 102001);
            }

            $result = $this->fundingApplyService->approveRisk(
                $orderNo,
                Auth::id() ?? 0
            );
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }

        return $this->succeed($result);
    }

    #[BodyParameter(name: 'order_no', description: '出金订单号（funding_apply.order_no）', type: 'integer')]
    #[BodyParameter(name: 'reason', description: '风控驳回原因', type: 'string')]
    /**
     * 驳回 funding_apply 出金风控
     *
     * @param FundingApplyRiskRejectRequest $request request
     *
     * @return JsonResponse
     */
    public function riskReject(FundingApplyRiskRejectRequest $request): JsonResponse
    {
        try {
            $result = $this->fundingApplyService->rejectRisk(
                (int) $request->input('order_no'),
                Auth::id() ?? 0,
                (string) $request->input('reason')
            );
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        }

        return $this->succeed($result);
    }

    #[BodyParameter(name: 'order_no', description: '出金订单号（funding_apply.order_no）', type: 'integer')]
    #[BodyParameter(name: 'reason', description: '财务驳回原因（处理中或失败状态可驳回）', type: 'string')]
    /**
     * 驳回 funding_apply 出金财务
     *
     * @param FundingApplyFinanceRejectRequest $request request
     *
     * @return JsonResponse
     */
    public function financeReject(FundingApplyFinanceRejectRequest $request): JsonResponse
    {
        $result = $this->fundingApplyService->rejectFinance(
            (int) $request->input('order_no'),
            Auth::id(),
            (string) $request->input('reason')
        );

        return $this->succeed($result);
    }

    #[BodyParameter(name: 'id', description: '出金申请ID（funding_apply.id）', type: 'integer')]
    #[BodyParameter(name: 'user_code', description: '用户ID', type: 'integer')]
    #[BodyParameter(name: 'hash', description: '交易哈希', type: 'string')]
    #[BodyParameter(name: 'platform', description: '渠道平台（可选）', type: 'string', required: false)]
    /**
     * funding_apply 出金财务审核通过（处理中或失败状态人工确认 hash）
     *
     * @param FundingApplyFinanceAuditRequest $request request
     *
     * @return JsonResponse
     */
    public function financeAudit(FundingApplyFinanceAuditRequest $request): JsonResponse
    {
        $orderNo = $this->fundingApplyService->resolveWithdrawalOrderNoByIdOrFail((int) $request->input('id'));
        $auditMeta = $this->fundingApplyService->getWithdrawalAuditMeta($orderNo);
        if ((int) ($auditMeta['user_code'] ?? 0) !== (int) $request->input('user_code')) {
            throw new \Exception(trans('public.message.data_error'));
        }

        $hasWithdrawalRestriction = $this->blackListService->hasWithdrawalRestriction((int) ($auditMeta['user_code'] ?? 0));
        if ($hasWithdrawalRestriction) {
            return $this->succeed([], trans('withdrawal.message.has_withdrawal_restriction'), 102001);
        }

        $auditResult = $this->fundingApplyService->retryFailedFinance(
            $orderNo,
            Auth::id() ?? 0,
            (string) $request->input('hash'),
            $request->input('platform')
        );

        return $this->succeed($auditResult);
    }

    /**
     * 重新发起钱包出金（财务重试）。
     *
     * 前端约定：
     * - retry_phase=retrying、last_error_code=WITHDRAWAL_NOT_SUBMITTED（处理中、自动重试等待）：点击立即用同一 request_id 重发一次，不消耗自动次数，可无限次点击
     * - status=5/risk_status=1/finance_status=3、retry_phase=exhausted、last_error_code=WITHDRAWAL_NOT_SUBMITTED（自动次数用完）：重置失败单后重新发起钱包出金，仍可无限次点击
     * - 仅 WITHDRAWAL_NOT_SUBMITTED 允许重试；其它错误码、历史失败单或已接管状态一律返回 final_status 错误
     * - 同一笔订单上一次重试请求未返回前再次点击，返回“钱包出金重试正在处理中”，不会并发发起第二次钱包请求
     * - 黑名单出金限制用户返回 code=102001
     */
    #[BodyParameter(name: 'order_no', description: '出金订单号（funding_apply.order_no）', type: 'integer', required: true)]
    #[Response(
        description: "返回最新 retry 状态；withdrawal 为 Walletd 受理结果（重试等待中无此字段）。字段核对规则：\n- request_id: Walletd 幂等键，格式 {order_no}@retry，一单终身复用\n- retryable: 仅 last_error_code 严格等于 WITHDRAWAL_NOT_SUBMITTED 时为 true\n- retry_phase: retrying=处理中自动重试等待；exhausted=自动重试耗尽后财务失败；submitted/cancelled/none 均不可再调本接口\n- auto_retry_count/auto_max_attempts: 自动重试已消耗次数/上限；手动重试不消耗自动次数\n- next_retry_at: 下次自动重试时间；手动重试可提前于该时间点击\n- retry_in_progress: 上一次请求未返回时为 true；此时再次调用返回“钱包出金重试正在处理中”\n- last_error_code/last_error_message: 最近钱包错误；仅 WITHDRAWAL_NOT_SUBMITTED 可重试\n- withdrawal.request_id/withdrawal.status: Walletd 受理结果，仅受理时返回",
        type: 'array{order_no:string, retry:array{request_id:string, retryable:bool, retry_phase:string, auto_retry_count:int, auto_max_attempts:int, next_retry_at:?string, retry_in_progress:bool, last_error_code:?string, last_error_message:?string}, withdrawal?:array{request_id:string, status:string}}',
        examples: [[
            'order_no' => '1001',
            'retry' => [
                'request_id' => '1001@retry',
                'retryable' => true,
                'retry_phase' => 'retrying',
                'auto_retry_count' => 1,
                'auto_max_attempts' => 3,
                'next_retry_at' => '2026-09-04 12:06:00',
                'retry_in_progress' => false,
                'last_error_code' => 'WITHDRAWAL_NOT_SUBMITTED',
                'last_error_message' => 'withdrawal was not submitted to PSP',
            ],
        ]]
    )]
    public function financeRetry(FundingApplyRiskApproveRequest $request): JsonResponse
    {
        $orderNo = (int) $request->input('order_no');
        $auditMeta = $this->fundingApplyService->getWithdrawalAuditMeta($orderNo);
        if ($this->blackListService->hasWithdrawalRestriction((int) ($auditMeta['user_code'] ?? 0))) {
            return $this->succeed([], trans('withdrawal.message.has_withdrawal_restriction'), 102001);
        }

        $auditResult = $this->fundingApplyService->manualFinanceRetry(
            $orderNo,
            Auth::id() ?? 0
        );

        return $this->succeed($auditResult);
    }

    private function getDepositList(FundingApplyListRequest $request, int $limit, int $offset): array
    {
        return $this->fundingApplyService->getDepositList(
            [
                'user_code' => $request->input('user_code'),
                'status' => $request->input('status'),
                'platform' => $request->input('platform'),
                'deposit_status' => $request->input('deposit_status'),
                'direction' => $request->input('direction'),
                'received_time_start' => $request->input('received_time_start'),
                'received_time_end' => $request->input('received_time_end'),
            ],
            $limit,
            $offset
        );
    }

    private function getWithdrawalRiskList(FundingApplyWithdrawalRiskListRequest $request, int $limit, int $offset): array
    {
        return $this->fundingApplyService->getWithdrawalRiskList(
            [
                'user_code' => $request->input('user_code'),
                'order_no' => $request->input('order_no'),
                'withdraw_time_start' => $request->input('withdraw_time_start'),
                'withdraw_time_end' => $request->input('withdraw_time_end'),
                'platform' => $request->input('platform'),
                'status' => $request->input('status'),
            ],
            $limit,
            $offset
        );
    }

    private function getWithdrawalFinanceList(FundingApplyWithdrawalFinanceListRequest $request, int $limit, int $offset): array
    {
        return $this->fundingApplyService->getWithdrawalFinanceList(
            [
                'user_code' => $request->input('user_code'),
                'order_no' => $request->input('order_no'),
                'withdraw_time_start' => $request->input('withdraw_time_start'),
                'withdraw_time_end' => $request->input('withdraw_time_end'),
                'platform' => $request->input('platform'),
                'status' => $request->input('status'),
            ],
            $limit,
            $offset
        );
    }
}
