<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Http\Controllers\BaseController;
use App\Http\Requests\CopyTrading\MasterApplicationReviewRequest;
use App\Http\Requests\CopyTrading\MasterCurrentFollowersRequest;
use App\Http\Requests\CopyTrading\MasterHistoryFollowersRequest;
use App\Http\Requests\CopyTrading\MasterProtectionTimeUpdateRequest;
use App\Services\Api\CopyTradingService;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Scaffold\Framework\Enums\TypeTradeAccount;

#[Group('CopyTrading', '高手/跟单 Mock 接口')]
class CopyTradingController extends BaseController
{
    public function __construct(private CopyTradingService $copyTradingService) {}

    #[QueryParameter(name: 'master_level', description: '高手等级编码（如 Level1）', type: 'string', required: false)]
    #[QueryParameter(name: 'level_name', description: '等级名称', type: 'string', required: false)]
    #[QueryParameter(name: 'is_initial', description: '是否初始默认等级：0否 1是', type: 'integer', required: false)]
    #[QueryParameter(name: 'is_default_level', description: '是否默认等级（固定为第一级）：0否 1是', type: 'integer', required: false)]
    #[QueryParameter(name: 'status', description: '状态：0禁用 1启用', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(type: 'array{data: array<int, array{level_id:int,master_level:string,is_initial:int,is_initial_text:string,is_default_level:int,is_default_level_text:string,level_name:string,balance_threshold:?string,order_count_30d_threshold:?int,profit_rate_30d_threshold:?string,follower_fund_threshold:?string,follower_count_threshold:?int,profit_share_rate:string,fee_commission_rate:string,max_invest_amount:string,status:int,status_text:string,created_at:string,updated_at:string}>, total_count:int, next_level_id:int}')]
    /**
     * 高手等级配置列表
     *
     * Query:
     * - master_level:string 高手等级编码
     * - level_name:string 等级名称
     * - is_initial:int 是否初始默认等级（1是，0否）
     * - is_default_level:int 是否默认等级（固定为第一级，1是，0否）
     * - status:int 状态（0禁用，1启用）
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - level_id:int 等级ID
     * - master_level:string 高手等级编码
     * - is_initial:int 是否初始默认等级（1是，0否）
     * - is_initial_text:string 是否初始默认等级文案
     * - is_default_level:int 是否默认等级（固定为第一级 Level1，1是，0否）
     * - is_default_level_text:string 是否默认等级文案
     * - level_name:string 等级名称
     * - balance_threshold:?string 余额门槛
     * - order_count_30d_threshold:?int 近30天交易次数门槛
     * - profit_rate_30d_threshold:?string 近30天收益率门槛
     * - follower_fund_threshold:?string 跟单规模门槛
     * - follower_count_threshold:?int 跟随人数门槛
     * - profit_share_rate:string 分润比例
     * - fee_commission_rate:string 手续费分佣比例（如50代表50%）
     * - max_invest_amount:string 最大可跟随金额
     * - status:int 状态（0禁用，1启用）
     * - status_text:string 状态文案
     * - created_at:string 创建时间
     * - updated_at:string 修改时间
     *
     * @param  Request  $request  request
     */
    public function masterLevels(Request $request): JsonResponse
    {
        $result = $this->copyTradingService->getMasterLevels(
            [
                'master_level' => $request->input('master_level'),
                'level_name' => $request->input('level_name'),
                'is_initial' => $request->input('is_initial'),
                'is_default_level' => $request->input('is_default_level'),
                'status' => $request->input('status'),
            ],
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[Response(type: 'array{master_levels: array<int, array{key:string,value:string}>, master_level_names: array<int, array{key:string,value:string}>, is_initial_options: array<int, array{key:string,value:string}>, has_commission_options: array<int, array{key:string,value:string}>, master_statuses: array<int, array{key:string,value:string}>, master_types: array<int, array{key:string,value:string}>, follow_statuses: array<int, array{key:string,value:string}>, trade_account_types: array<int, array{key:string,value:string}>}')]
    /**
     * 高手等级配置选项
     *
     * Response.fields:
     * - master_levels:array 高手等级列表（key/value）
     * - master_level_names:array 高手等级名称列表（key/value）
     * - is_initial_options:array 是否初始默认等级列表（key/value，1是，0否）
     * - has_commission_options:array 是否可产生分佣列表（key/value，1是，0否）
     * - master_statuses:array 高手状态列表（key/value）
     * - master_types:array 高手类型列表（key/value）
     * - follow_statuses:array 跟随状态列表（key/value，0跟随中，1已结束）
     * - trade_account_types:array 交易账号类型列表（key/value，仅返回0统一交易账号、3赠金账户）
     */
    public function masterLevelOptions(): JsonResponse
    {
        return $this->succeed($this->copyTradingService->getMasterLevelOptions());
    }

    #[Response(type: 'array{status_options: array<int, array{key:string,value:string}>}')]
    /**
     * 高手申请筛选项
     *
     * Response.fields:
     * - status_options:array 申请状态列表（key/value，1处理中，2通过，3拒绝）
     */
    public function masterApplicationOptions(): JsonResponse
    {
        return $this->succeed($this->copyTradingService->getMasterApplicationOptions());
    }

    #[QueryParameter(name: 'user_code', description: '用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'status', description: '审核状态：1处理中，2通过，3拒绝', type: 'integer', required: false)]
    #[QueryParameter(name: 'operator_name', description: '操作人姓名', type: 'string', required: false)]
    #[QueryParameter(name: 'applied_at_start', description: '申请开始时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'applied_at_end', description: '申请结束时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(type: 'array{data: array<int, array{id:int,user_code:int,trade_platform:string,project:string,display_name:string,project_type:string,profit_share_rate:string,initial_funding:string,bio:string,operator_name:string,rejection_reason:string,applied_at:string,status:int,status_text:string}>, total_count:int}')]
    /**
     * 高手申请列表
     *
     * Query:
     * - user_code:int 用户ID
     * - status:int 审核状态（1处理中，2通过，3拒绝）
     * - operator_name:string 操作人姓名
     * - applied_at_start:string 申请开始时间
     * - applied_at_end:string 申请结束时间
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - user_code:int 用户ID
     * - trade_platform:string 交易平台
     * - project:string 交易类型
     * - display_name:string 账户信息
     * - project_type:string 高手类型
     * - profit_share_rate:string 分润比例
     * - initial_funding:string 带单规模
     * - bio:string 个人简介
     * - operator_name:string 操作人
     * - rejection_reason:string 拒绝原因
     * - applied_at:string 申请时间
     * - status:int 审核状态
     * - status_text:string 审核状态文案
     */
    public function masterApplications(Request $request): JsonResponse
    {
        $result = $this->copyTradingService->getMasterApplications(
            [
                'user_code' => $request->input('user_code'),
                'status' => $request->input('status'),
                'operator_name' => $request->input('operator_name'),
                'applied_at_start' => $request->input('applied_at_start'),
                'applied_at_end' => $request->input('applied_at_end'),
            ],
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[QueryParameter(name: 'user_code', description: '用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'status', description: '审核状态：1处理中，2通过，3拒绝', type: 'integer', required: false)]
    #[QueryParameter(name: 'operator_name', description: '操作人姓名', type: 'string', required: false)]
    #[QueryParameter(name: 'applied_at_start', description: '申请开始时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'applied_at_end', description: '申请结束时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    /**
     * 高手申请列表导出
     *
     * Query:
     * - user_code:int 用户ID
     * - status:int 审核状态（1处理中，2通过，3拒绝）
     * - operator_name:string 操作人姓名
     * - applied_at_start:string 申请开始时间
     * - applied_at_end:string 申请结束时间
     */
    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function exportMasterApplications(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $filters = [
            'user_code' => $request->input('user_code'),
            'status' => $request->input('status'),
            'operator_name' => $request->input('operator_name'),
            'applied_at_start' => $request->input('applied_at_start'),
            'applied_at_end' => $request->input('applied_at_end'),
        ];

        $preview = $this->copyTradingService->getMasterApplications($filters, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $result = $this->copyTradingService->getMasterApplications(
            $filters,
            (int) ($preview['total_count'] ?? 0),
            0
        );
        $rows = array_map(static function (array $item): array {
            return [
                'trade_platform' => $item['trade_platform'] ?? '',
                'project' => $item['project'] ?? '',
                'display_name' => $item['display_name'] ?? '',
                'project_type' => $item['project_type'] ?? '',
                'profit_share_rate' => $item['profit_share_rate'] ?? '',
                'initial_funding' => $item['initial_funding'] ?? '',
                'operator_name' => $item['operator_name'] ?? '',
                'applied_at' => $item['applied_at'] ?? '',
                'status_text' => $item['status_text'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('copytrading-master-applications-%s', now()->format('YmdHis')),
            trans('copytrading.export.master_applications.headers'),
            $rows,
            $exportExtension
        );
    }

    #[PathParameter(name: 'id', description: '高手申请ID', type: 'integer', required: true)]
    #[BodyParameter('status', '审核结果：2通过，3拒绝', required: true, type: 'integer')]
    #[BodyParameter('rejection_reason', '拒绝原因，拒绝时必填', required: false, type: 'string')]
    #[Response(type: 'bool')]
    /**
     * 审核高手申请
     *
     * Path:
     * - id:int 高手申请ID
     *
     * Body:
     * - status:int 审核结果（2通过，3拒绝）
     * - rejection_reason:string 拒绝原因，拒绝时必填
     */
    public function reviewMasterApplication(MasterApplicationReviewRequest $request, int $id): JsonResponse
    {
        $this->copyTradingService->reviewMasterApplication(
            $id,
            (int) $request->input('status'),
            $request->input('rejection_reason')
        );

        return $this->succeed(true);
    }

    #[BodyParameter('locale_configs', '多语言配置[{lang,name}]', required: true, type: 'array<int, array{lang:string,name:string}>')]
    #[BodyParameter('balance_threshold', '余额阈值（可选）', required: false, type: 'string')]
    #[BodyParameter('order_count_30d_threshold', '近30天交易次数阈值（可选）', required: false, type: 'integer')]
    #[BodyParameter('profit_rate_30d_threshold', '近30天收益率阈值（可选）', required: false, type: 'string')]
    #[BodyParameter('follower_fund_threshold', '跟单规模阈值（可选）', required: false, type: 'string')]
    #[BodyParameter('follower_count_threshold', '跟随人数阈值（可选）', required: false, type: 'integer')]
    #[BodyParameter('profit_share_rate', '分润比例', required: true, type: 'string')]
    #[BodyParameter('fee_commission_rate', '手续费分佣比例（如50代表50%）', required: true, type: 'string')]
    #[BodyParameter('max_invest_amount', '最大可跟随金额', required: true, type: 'string')]
    #[BodyParameter('is_initial', '是否初始默认等级：0否 1是，非必填', required: false, type: 'integer')]
    #[BodyParameter('status', '状态：0禁用 1启用，默认1', required: false, type: 'integer')]
    #[Response(type: 'bool')]
    /**
     * 新增高手等级
     *
     * Body:
     * - locale_configs:array 多语言配置[{lang,name}]
     * - balance_threshold:string 余额门槛（可选）
     * - order_count_30d_threshold:int 近30天交易次数门槛（可选）
     * - profit_rate_30d_threshold:string 近30天收益率门槛（可选）
     * - follower_fund_threshold:string 跟单规模门槛（可选）
     * - follower_count_threshold:int 跟随人数门槛（可选）
     * - profit_share_rate:string 分润比例
     * - fee_commission_rate:string 手续费分佣比例（如50代表50%）
     * - max_invest_amount:string 最大可跟随金额
     * - is_initial:int 是否初始默认等级（0否，1是，非必填）
     * - status:int 状态（0禁用，1启用，默认1）
     *
     * @param  Request  $request  request
     */
    public function createMasterLevel(Request $request): JsonResponse
    {
        $this->validateMasterLevelPayload($request);
        $this->copyTradingService->createMasterLevel($request->all());

        return $this->succeed(true);
    }

    #[PathParameter(name: 'level_id', description: '等级ID', type: 'integer')]
    #[BodyParameter('locale_configs', '多语言配置[{lang,name}]', required: true, type: 'array<int, array{lang:string,name:string}>')]
    #[BodyParameter('balance_threshold', '余额阈值（可选）', required: false, type: 'string')]
    #[BodyParameter('order_count_30d_threshold', '近30天交易次数阈值（可选）', required: false, type: 'integer')]
    #[BodyParameter('profit_rate_30d_threshold', '近30天收益率阈值（可选）', required: false, type: 'string')]
    #[BodyParameter('follower_fund_threshold', '跟单规模阈值（可选）', required: false, type: 'string')]
    #[BodyParameter('follower_count_threshold', '跟随人数阈值（可选）', required: false, type: 'integer')]
    #[BodyParameter('profit_share_rate', '分润比例', required: true, type: 'string')]
    #[BodyParameter('fee_commission_rate', '手续费分佣比例（如50代表50%）', required: true, type: 'string')]
    #[BodyParameter('max_invest_amount', '最大可跟随金额', required: true, type: 'string')]
    #[BodyParameter('is_initial', '是否初始默认等级：0否 1是，非必填；不传则保持原值', required: false, type: 'integer')]
    #[BodyParameter('status', '状态：0禁用 1启用，默认1', required: false, type: 'integer')]
    /**
     * 修改高手等级
     *
     * Path:
     * - level_id:int 等级ID
     *
     * Body:
     * - locale_configs:array 多语言配置[{lang,name}]
     * - balance_threshold:string 余额门槛（可选）
     * - order_count_30d_threshold:int 近30天交易次数门槛（可选）
     * - profit_rate_30d_threshold:string 近30天收益率门槛（可选）
     * - follower_fund_threshold:string 跟单规模门槛（可选）
     * - follower_count_threshold:int 跟随人数门槛（可选）
     * - profit_share_rate:string 分润比例
     * - fee_commission_rate:string 手续费分佣比例（如50代表50%）
     * - max_invest_amount:string 最大可跟随金额
     * - is_initial:int 是否初始默认等级（0否，1是，非必填；不传则保持原值）
     * - status:int 状态（0禁用，1启用，默认1）
     *
     * @param  Request  $request  request
     * @param  int  $level_id  等级ID
     */
    public function updateMasterLevel(Request $request, int $level_id): JsonResponse
    {
        $this->validateMasterLevelPayload($request);
        $this->copyTradingService->updateMasterLevel($level_id, $request->all());

        return $this->succeed(true);
    }

    private function validateMasterLevelPayload(Request $request): void
    {
        foreach (['balance_threshold', 'order_count_30d_threshold', 'profit_rate_30d_threshold', 'follower_fund_threshold', 'follower_count_threshold', 'is_initial'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $langKeys = array_keys(config('lang.languages', []));

        $request->validate([
            'locale_configs' => ['required', 'array', 'min:1'],
            'locale_configs.*.lang' => ['required', 'string', Rule::in($langKeys)],
            'locale_configs.*.name' => ['required', 'string'],
            'balance_threshold' => ['nullable', 'numeric'],
            'order_count_30d_threshold' => ['nullable', 'integer', 'min:0'],
            'profit_rate_30d_threshold' => ['nullable', 'numeric'],
            'follower_fund_threshold' => ['nullable', 'numeric'],
            'follower_count_threshold' => ['nullable', 'integer', 'min:0'],
            'profit_share_rate' => ['required', 'numeric'],
            'fee_commission_rate' => ['required', 'numeric'],
            'max_invest_amount' => ['required', 'numeric'],
            'is_initial' => ['nullable', 'boolean'],
            'status' => ['nullable', 'integer'],
        ], [
            'locale_configs.required' => trans('public.message.in_complete'),
            'locale_configs.array' => trans('public.message.data_error'),
            'locale_configs.min' => trans('public.message.in_complete'),
            'locale_configs.*.lang.required' => trans('public.message.in_complete'),
            'locale_configs.*.lang.in' => trans('public.message.lang_err'),
            'locale_configs.*.name.required' => trans('public.message.in_complete'),
            'follower_fund_threshold.numeric' => trans('copytrading.message.follower_fund_threshold_numeric'),
            'follower_count_threshold.integer' => trans('copytrading.message.follower_count_threshold_integer'),
        ]);

        if (! $request->has('status')) {
            $request->merge(['status' => 1]);
        }

        $followerFund = $request->input('follower_fund_threshold');
        $maxInvestAmount = $request->input('max_invest_amount');
        if (is_numeric($followerFund) && is_numeric($maxInvestAmount) && (float) $maxInvestAmount < (float) $followerFund) {
            throw new BusinessException(trans('copytrading.message.max_invest_amount_gte_follower_fund'));
        }
    }

    #[PathParameter(name: 'level_id', description: '等级ID', type: 'integer')]
    #[Response(type: 'bool')]
    /**
     * 删除高手等级（逻辑删除）
     *
     * Path:
     * - level_id:int 等级ID
     *
     * @param  int  $level_id  等级ID
     */
    public function deleteMasterLevel(int $level_id): JsonResponse
    {
        $this->copyTradingService->deleteMasterLevel($level_id);

        return $this->succeed(true);
    }

    #[QueryParameter(name: 'master_user_code', description: '高手ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'trade_account_id', description: '交易账号ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'master_level', description: '高手等级', type: 'string', required: false)]
    #[QueryParameter(name: 'trade_account_type', description: '账号类型：0统一交易账号，3赠金账户', type: 'integer', required: false)]
    #[QueryParameter(name: 'status', description: '状态：0申请中 1成功 2拒绝 3已失效 4隐藏', type: 'integer', required: false)]
    #[QueryParameter(name: 'is_private', description: '是否私密：0公开展示 1私密高手', type: 'integer', required: false)]
    #[QueryParameter(name: 'is_manual', description: '是否人工成为高手：0自动 1人工', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(type: 'array{data: array<int, array{master_user_code:int,trade_account_id:int,trade_account_type:int,trade_account_type_name:string,trade_account_type_text:string,master_level:string,balance:string,order_count_30d:int,profit_rate_30d:string,aum:string,investor_count:int,commission_follower_count:int,obtained_profit_share_amount:string,profit_share_rate:string,fee_commission_rate:string,obtained_fee_commission_amount:string,current_hidden_days:?int,level_protection_started_at:string,level_protection_ended_at:string,protection_status:int,protection_status_text:string,status:int,status_text:string,is_private:int,is_private_text:string,can_manual_delist:int,is_manual:int,reason:string}>, total_count:int}')]
    /**
     * 高手管理列表
     *
     * Query:
     * - master_user_code:int 高手ID
     * - trade_account_id:int 交易账号ID
     * - master_level:string 高手等级
     * - trade_account_type:int 账号类型（0统一交易账号，3赠金账户）
     * - status:int 状态（0申请中，1成功，2拒绝，3已失效，4隐藏）
     * - is_private:int 是否私密（0公开展示，1私密高手）
     * - is_manual:int 是否人工成为高手（0自动，1人工）
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - master_user_code:int 高手ID
     * - trade_account_id:int 交易账号ID
     * - trade_account_type:int 账号类型
     * - trade_account_type_name:string 账号类型文案
     * - trade_account_type_text:string 账号类型文案
     * - master_level:string 高手等级
     * - balance:string 余额
     * - order_count_30d:int 近30天平仓订单数
     * - profit_rate_30d:string 近30天收益率
     * - aum:string 跟单规模
     * - investor_count:int 跟随人数
     * - commission_follower_count:int 分佣人数（仅统计无代理归属的真实跟随者）
     * - obtained_profit_share_amount:string 已获得分润金额
     * - profit_share_rate:string 分润比例
     * - fee_commission_rate:string 手续费分佣比例（如50代表50%，取当前等级配置）
     * - obtained_fee_commission_amount:string 已获得手续费分佣金额（已结算记录汇总）
     * - current_hidden_days:?int 当前隐藏天数
     * - level_protection_started_at:string 等级保护开始时间
     * - level_protection_ended_at:string 等级保护截止时间
     * - protection_status:int 保护状态（0未生效，1保护中，2已结束）
     * - protection_status_text:string 保护状态文案
     * - status:int 状态（0申请中，1成功，2拒绝，3已失效，4隐藏）
     * - status_text:string 状态文案
     * - is_private:int 是否私密（0公开展示，1私密高手）
     * - is_private_text:string 是否私密文案
     * - can_manual_delist:int 是否可手动移除（1可，0不可）
     * - is_manual:int 是否人工成为高手（1人工，0自动）
     * - reason:string 最近一次状态变更原因
     *
     * @param  Request  $request  request
     */
    public function masters(Request $request): JsonResponse
    {
        $result = $this->copyTradingService->getMasters(
            [
                'master_user_code' => $request->input('master_user_code'),
                'trade_account_id' => $request->input('trade_account_id'),
                'master_level' => $request->input('master_level'),
                'trade_account_type' => $request->input('trade_account_type'),
                'status' => $request->input('status'),
                'is_private' => $request->input('is_private'),
                'is_manual' => $request->input('is_manual'),
            ],
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[QueryParameter(name: 'master_user_code', description: '高手ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'trade_account_id', description: '交易账号ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'master_level', description: '高手等级', type: 'string', required: false)]
    #[QueryParameter(name: 'trade_account_type', description: '账号类型：0统一交易账号，3赠金账户', type: 'integer', required: false)]
    #[QueryParameter(name: 'status', description: '状态：0申请中 1成功 2拒绝 3已失效 4隐藏', type: 'integer', required: false)]
    #[QueryParameter(name: 'is_private', description: '是否私密：0公开展示 1私密高手', type: 'integer', required: false)]
    #[QueryParameter(name: 'is_manual', description: '是否人工成为高手：0自动 1人工', type: 'integer', required: false)]
    /**
     * 高手管理导出
     *
     * Query:
     * - master_user_code:int 高手ID
     * - trade_account_id:int 交易账号ID
     * - master_level:string 高手等级
     * - trade_account_type:int 账号类型（0统一交易账号，3赠金账户）
     * - status:int 状态（0申请中，1成功，2拒绝，3已失效，4隐藏）
     * - is_private:int 是否私密（0公开展示，1私密高手）
     * - is_manual:int 是否人工成为高手（0自动，1人工）
     */
    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function exportMasters(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $filters = [
            'master_user_code' => $request->input('master_user_code'),
            'trade_account_id' => $request->input('trade_account_id'),
            'master_level' => $request->input('master_level'),
            'trade_account_type' => $request->input('trade_account_type'),
            'status' => $request->input('status'),
            'is_private' => $request->input('is_private'),
            'is_manual' => $request->input('is_manual'),
        ];

        $preview = $this->copyTradingService->getMasters(
            $filters,
            1,
            0
        );
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $result = $this->copyTradingService->getMasters(
            $filters,
            (int) ($preview['total_count'] ?? 0),
            0
        );

        $rows = array_map(static function (array $item): array {
            return [
                'master_user_code' => $item['master_user_code'] ?? '',
                'trade_account_id' => $item['trade_account_id'] ?? '',
                'trade_account_type' => $item['trade_account_type_text'] ?? ($item['trade_account_type_name'] ?? ''),
                'master_level' => $item['master_level'] ?? '',
                'balance' => $item['balance'] ?? '',
                'order_count_30d' => $item['order_count_30d'] ?? '',
                'profit_rate_30d' => $item['profit_rate_30d'] ?? '',
                'aum' => $item['aum'] ?? '',
                'investor_count' => $item['investor_count'] ?? '',
                'obtained_profit_share_amount' => $item['obtained_profit_share_amount'] ?? '',
                'profit_share_rate' => $item['profit_share_rate'] ?? '',
                'current_hidden_days' => $item['current_hidden_days'] ?? '',
                'level_protection_started_at' => $item['level_protection_started_at'] ?? '',
                'level_protection_ended_at' => $item['level_protection_ended_at'] ?? '',
                'protection_status_text' => $item['protection_status_text'] ?? '',
                'reason' => $item['reason'] ?? '',
                'status' => $item['status_text'] ?? '',
            ];
        }, $result['data']);

        return ExportUtil::exportExcelDirect(
            sprintf('copytrading-masters-%s', now()->format('YmdHis')),
            trans('copytrading.export.masters.headers'),
            $rows,
            $exportExtension
        );
    }

    #[PathParameter(name: 'trade_account_id', description: '交易账号ID', type: 'integer')]
    #[BodyParameter('status', '操作：1=设为公开，3=移除高手，5=设为私密', required: true, type: 'integer')]
    #[BodyParameter('reason', '原因', required: false, type: 'string')]
    #[Response(type: 'bool')]
    /**
     * 高手管理操作（设为公开 / 设为私密 / 移除高手）。
     *
     * 各操作说明：
     * - 设为公开 (status=1)：状态变更为正常，类型变更为公开。当前已是 成功+公开 时不可操作。
     * - 设为私密 (status=5)：状态变更为正常，类型变更为私密。当前已是 成功+私密 时不可操作。
     * - 移除高手 (status=3)：状态变更为已失效。当前为 申请中/拒绝/已失效 时不可操作。
     *
     * Path:
     * - trade_account_id:int 交易账号ID
     *
     * Body:
     * - status:int 操作（1=设为公开，3=移除高手，5=设为私密）
     * - reason:string 原因
     *
     * @param  Request  $request  request
     * @param  int  $trade_account_id  交易账号ID
     */
    public function changeStatus(Request $request, int $trade_account_id): JsonResponse
    {
        $this->copyTradingService->changeMasterStatus(
            $trade_account_id,
            (int) $request->input('status'),
            (int) (Auth::id() ?? 0),
            (string) $request->input('reason', '')
        );

        return $this->succeed(true);
    }

    #[BodyParameter('trade_account_id', '交易账号ID（单个更新时传）', required: false, type: 'integer')]
    #[BodyParameter('trade_account_ids', '交易账号ID列表（批量更新时传）', required: false, type: 'array<int, int>')]
    #[BodyParameter('level_protection_started_at', '等级保护开始时间', required: false, type: 'string')]
    #[BodyParameter('level_protection_ended_at', '等级保护结束时间', required: false, type: 'string')]
    #[Response(type: 'bool')]
    /**
     * 修改高手等级保护时间，支持单个或批量。
     *
     * Body:
     * - trade_account_id:int 交易账号ID（单个更新时传）
     * - trade_account_ids:array<int> 交易账号ID列表（批量更新时传）
     * - level_protection_started_at:string 等级保护开始时间
     * - level_protection_ended_at:string 等级保护结束时间
     */
    public function updateMasterProtectionTime(MasterProtectionTimeUpdateRequest $request): JsonResponse
    {
        $this->copyTradingService->updateMasterProtectionTime(
            $request->input('trade_account_id'),
            $request->input('trade_account_ids', []),
            $request->input('level_protection_started_at'),
            $request->input('level_protection_ended_at')
        );

        return $this->succeed(true);
    }

    #[QueryParameter(name: 'user_code', description: '用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'trade_account_id', description: '交易账号ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'trade_account_type', description: '账号类型：0统一交易账号，3赠金账户', type: 'integer', required: false)]
    #[QueryParameter(name: 'min_balance', description: '余额 >= 该值', type: 'string', required: false)]
    #[QueryParameter(name: 'min_order_count_30d', description: '近30天交易次数 >= 该值', type: 'integer', required: false)]
    #[QueryParameter(name: 'min_profit_rate_30d', description: '近30天收益率 >= 该值（百分比，如 10 表示 >= 10%）', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(type: 'array{data: array<int, array{user_code:int,trade_account_id:int,trade_account_type:int,trade_account_type_name:string,trade_account_type_text:string,is_whitelist:int,is_whitelist_text:string,balance:string,order_count_30d:int,profit_rate_30d:string}>, total_count:int}')]
    /**
     * 潜在高手列表。
     *
     * 以用户表为主表，关联交易账号表，展示未在高手表(master_account)中的用户及其近30日交易统计。
     * 以交易账号维度展示，每个交易账号独立一行。
     * 30日统计口径与高手管理列表一致（平仓订单，cmd < 2，close_time 近30天）。
     *
     * Query:
     * - user_code:int 用户ID（精确查询）
     * - trade_account_id:int 交易账号ID（精确查询）
     * - trade_account_type:int 账号类型（0统一交易账号，3赠金账户）
     * - min_balance:string 余额 >= 该值
     * - min_order_count_30d:int 近30天交易次数 >= 该值
     * - min_profit_rate_30d:string 近30天收益率 >= 该值（百分比）
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - user_code:int 用户ID
     * - trade_account_id:int 交易账号ID
     * - trade_account_type:int 账号类型
     * - trade_account_type_name:string 账号类型文案
     * - trade_account_type_text:string 账号类型展示文案
     * - is_whitelist:int 特殊客户类型（0正常 1白名单 2黑名单）
     * - is_whitelist_text:string 特殊客户文案
     * - balance:string 余额
     * - order_count_30d:int 近30天交易次数
     * - profit_rate_30d:string 近30天收益率
     *
     * @param  Request  $request  request
     */
    public function potentialMasters(Request $request): JsonResponse
    {
        $result = $this->copyTradingService->getPotentialMasters(
            [
                'user_code' => $request->input('user_code'),
                'trade_account_id' => $request->input('trade_account_id'),
                'trade_account_type' => $request->input('trade_account_type'),
                'min_balance' => $request->input('min_balance'),
                'min_order_count_30d' => $request->input('min_order_count_30d'),
                'min_profit_rate_30d' => $request->input('min_profit_rate_30d'),
            ],
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    /**
     * 潜在高手导出
     *
     * Query:
     * - user_code:int 用户ID
     * - trade_account_id:int 交易账号ID
     * - trade_account_type:int 账号类型（0统一交易账号，3赠金账户）
     * - min_balance:string 余额 >= 该值
     * - min_order_count_30d:int 近30天交易次数 >= 该值
     * - min_profit_rate_30d:string 近30天收益率 >= 该值
     */
    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function exportPotentialMasters(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $filters = [
            'user_code' => $request->input('user_code'),
            'trade_account_id' => $request->input('trade_account_id'),
            'trade_account_type' => $request->input('trade_account_type'),
            'min_balance' => $request->input('min_balance'),
            'min_order_count_30d' => $request->input('min_order_count_30d'),
            'min_profit_rate_30d' => $request->input('min_profit_rate_30d'),
        ];

        $preview = $this->copyTradingService->getPotentialMasters(
            $filters,
            1,
            0
        );
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $result = $this->copyTradingService->getPotentialMasters(
            $filters,
            (int) ($preview['total_count'] ?? 0),
            0
        );

        $rows = array_map(static function (array $item): array {
            return [
                'user_code' => $item['user_code'] ?? '',
                'trade_account_id' => $item['trade_account_id'] ?? '',
                'trade_account_type' => $item['trade_account_type_text'] ?? ($item['trade_account_type_name'] ?? ''),
                'is_whitelist' => $item['is_whitelist_text'] ?? '',
                'balance' => $item['balance'] ?? '',
                'order_count_30d' => $item['order_count_30d'] ?? '',
                'profit_rate_30d' => $item['profit_rate_30d'] ?? '',
            ];
        }, $result['data']);

        return ExportUtil::exportExcelDirect(
            sprintf('copytrading-potential-masters-%s', now()->format('YmdHis')),
            trans('copytrading.export.potential_masters.headers'),
            $rows,
            $exportExtension
        );
    }

    #[PathParameter(name: 'trade_account_id', description: '交易账号ID', type: 'integer')]
    #[BodyParameter('status', '操作：1=公开高手（成为高手+公开），5=设为私密（成为高手+私密）', required: true, type: 'integer')]
    #[BodyParameter('reason', '原因', required: true, type: 'string')]
    #[Response(type: 'bool')]
    /**
     * 潜在高手操作（公开高手 / 设为私密）。
     *
     * 支持的操作：
     * - 公开高手 (status=1)：成为高手，状态变更为正常，类型变更为公开展示
     * - 设为私密 (status=5)：成为高手，状态变更为正常，类型变更为私密高手
     *
     * 操作完成后，该账号会自动出现在高手列表，并从潜在高手列表消失。
     *
     * Path:
     * - trade_account_id:int 交易账号ID
     *
     * Body:
     * - status:int 操作（1=公开高手，5=设为私密）
     * - reason:string 原因
     *
     * @param  Request  $request  request
     * @param  int  $trade_account_id  交易账号ID
     */
    public function changePotentialMasterStatus(Request $request, int $trade_account_id): JsonResponse
    {
        $this->copyTradingService->changePotentialMasterStatus(
            $trade_account_id,
            (int) $request->input('status'),
            (int) (Auth::id() ?? 0),
            (string) $request->input('reason', '')
        );

        return $this->succeed(true);
    }

    #[QueryParameter(name: 'master_user_code', description: '高手ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'master_trade_account_id', description: '高手交易账号ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'master_trade_account_type', description: '高手交易账号类型', type: 'integer', required: false)]
    #[QueryParameter(name: 'follower_user_code', description: '跟单者ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'follow_status', description: '0跟随中 1已结束', type: 'integer', required: false)]
    #[QueryParameter(name: 'has_commission', description: '是否可产生分佣：1可，0不可', type: 'integer', required: false)]
    #[QueryParameter(name: 'copy_start_time_start', description: '复制开始时间-开始', type: 'string', required: false)]
    #[QueryParameter(name: 'copy_start_time_end', description: '复制开始时间-结束', type: 'string', required: false)]
    #[QueryParameter(name: 'copy_end_time_start', description: '复制结束时间-开始', type: 'string', required: false)]
    #[QueryParameter(name: 'copy_end_time_end', description: '复制结束时间-结束', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(type: 'array{data: array<int, array{master_user_code:int,follower_user_code:int,master_trade_account_id:int,follower_trade_account_id:int,master_trade_account_type_name:string,follower_trade_account_type_name:string,invest_amount:string,current_invest_amount:string,master_level:string,copy_start_time:string,copy_end_time:?string,follow_status:int,follow_status_text:string,has_commission:int,has_commission_text:string}>, total_count:int}')]
    /**
     * 跟随关系列表
     *
     * Query:
     * - master_user_code:int 高手ID
     * - master_trade_account_id:int 高手交易账号ID
     * - master_trade_account_type:int 高手交易账号类型
     * - follower_user_code:int 跟单者ID
     * - follow_status:int 状态（0跟随中，1已结束）
     * - has_commission:int 是否可产生分佣（1可，0不可）
     * - copy_start_time_start:string 复制开始时间起
     * - copy_start_time_end:string 复制开始时间止
     * - copy_end_time_start:string 复制结束时间起
     * - copy_end_time_end:string 复制结束时间止
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - master_user_code:int 高手ID
     * - follower_user_code:int 跟单者ID
     * - master_trade_account_id:int 高手交易账号ID
     * - follower_trade_account_id:int 跟单者交易账号ID
     * - master_trade_account_type_name:string 高手交易账号类型
     * - follower_trade_account_type_name:string 跟单者交易账号类型
     * - invest_amount:string 跟随金额
     * - current_invest_amount:string 跟随金额（含盈亏）
     * - master_level:string 高手等级
     * - copy_start_time:string 复制开始时间
     * - copy_end_time:?string 复制结束时间
     * - follow_status:int 状态（0跟随中，1已结束）
     * - follow_status_text:string 状态文案
     * - has_commission:int 是否可产生分佣（1是，0否；跟随中且跟随者为无代理归属的直客）
     * - has_commission_text:string 是否可产生分佣文案
     *
     * @param  Request  $request  request
     */
    public function followRelations(Request $request): JsonResponse
    {
        $result = $this->copyTradingService->getFollowRelations(
            [
                'master_user_code' => $request->input('master_user_code'),
                'master_trade_account_id' => $request->input('master_trade_account_id'),
                'master_trade_account_type' => $request->input('master_trade_account_type'),
                'follower_user_code' => $request->input('follower_user_code'),
                'follow_status' => $request->input('follow_status'),
                'has_commission' => $request->input('has_commission'),
                'copy_start_time_start' => $request->input('copy_start_time_start'),
                'copy_start_time_end' => $request->input('copy_start_time_end'),
                'copy_end_time_start' => $request->input('copy_end_time_start'),
                'copy_end_time_end' => $request->input('copy_end_time_end'),
            ],
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[QueryParameter(name: 'master_user_code', description: '高手ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'master_trade_account_id', description: '高手交易账号ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'master_trade_account_type', description: '高手交易账号类型', type: 'integer', required: false)]
    #[QueryParameter(name: 'follower_user_code', description: '跟单者ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'follow_status', description: '0跟随中 1已结束', type: 'integer', required: false)]
    #[QueryParameter(name: 'copy_start_time_start', description: '复制开始时间-开始', type: 'string', required: false)]
    #[QueryParameter(name: 'copy_start_time_end', description: '复制开始时间-结束', type: 'string', required: false)]
    #[QueryParameter(name: 'copy_end_time_start', description: '复制结束时间-开始', type: 'string', required: false)]
    #[QueryParameter(name: 'copy_end_time_end', description: '复制结束时间-结束', type: 'string', required: false)]
    /**
     * 跟随关系导出
     */
    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function exportFollowRelations(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $result = $this->copyTradingService->getFollowRelations(
            [
                'master_user_code' => $request->input('master_user_code'),
                'master_trade_account_id' => $request->input('master_trade_account_id'),
                'master_trade_account_type' => $request->input('master_trade_account_type'),
                'follower_user_code' => $request->input('follower_user_code'),
                'follow_status' => $request->input('follow_status'),
                'copy_start_time_start' => $request->input('copy_start_time_start'),
                'copy_start_time_end' => $request->input('copy_start_time_end'),
                'copy_end_time_start' => $request->input('copy_end_time_start'),
                'copy_end_time_end' => $request->input('copy_end_time_end'),
            ],
            -1,
            -1
        );

        $rows = array_map(static function (array $item): array {
            return [
                'master_user_code' => $item['master_user_code'] ?? '',
                'follower_user_code' => $item['follower_user_code'] ?? '',
                'master_trade_account_id' => $item['master_trade_account_id'] ?? '',
                'follower_trade_account_id' => $item['follower_trade_account_id'] ?? '',
                'master_trade_account_type_name' => $item['master_trade_account_type_name'] ?? '',
                'follower_trade_account_type_name' => $item['follower_trade_account_type_name'] ?? '',
                'invest_amount' => $item['invest_amount'] ?? '',
                'current_invest_amount' => $item['current_invest_amount'] ?? '',
                'master_level' => $item['master_level'] ?? '',
                'copy_start_time' => $item['copy_start_time'] ?? '',
                'copy_end_time' => $item['copy_end_time'] ?? '',
                'follow_status' => $item['follow_status_text'] ?? '',
            ];
        }, $result['data']);

        $headers = trans('copytrading.export.follow_relations.headers');
        if (! is_array($headers)) {
            $headers = ['高手ID', '跟单者ID', '高手交易账号ID', '跟单者交易账号ID', '高手交易账号类型', '跟单者交易账号类型', '跟随金额', '跟随金额（含盈亏）', '高手等级', '复制开始时间', '复制结束时间', '状态'];
        }

        return ExportUtil::exportExcelDirect(
            sprintf('copytrading-follow-relations-%s', now()->format('YmdHis')),
            $headers,
            $rows,
            $exportExtension
        );
    }

    #[PathParameter(name: 'user_code', description: '客户ID', type: 'integer')]
    #[Response(type: 'array<int, array{master_nickname:string,invest_current_amount:string,invest_initial_amount:string,invest_start_time:string}>')]
    /**
     * 客户详情-跟随的高手
     *
     * Path:
     * - user_code:int 客户ID
     *
     * Response: array
     *
     * Response[].fields:
     * - master_nickname:string 高手昵称
     * - invest_current_amount:string 投资当前资金
     * - invest_initial_amount:string 投资初始资金
     * - invest_start_time:string 投资开始时间
     *
     * @param  int  $user_code  客户ID
     */
    public function customerCurrentFollowedMasters(int $user_code): JsonResponse
    {
        $result = $this->copyTradingService->customerCurrentFollowedMasters($user_code);
        if (! $result['is_succ']) {
            return $this->fail($result['message']);
        }

        return $this->succeed($result['data'], $result['message']);
    }

    #[PathParameter(name: 'user_code', description: '客户ID', type: 'integer')]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(type: 'array{data: array<int, array{master_nickname:string,invest_end_amount:string,invest_initial_amount:string,invest_start_time:string,invest_end_time:string,roi_rate:string}>, total_count:int}')]
    /**
     * 客户详情-历史跟随高手
     *
     * Path:
     * - user_code:int 客户ID
     *
     * Query:
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.fields:
     * - data:array 历史投资列表
     * - total_count:int 总数
     *
     * Response.data[].fields:
     * - master_nickname:string 高手昵称
     * - invest_end_amount:string 投资结束资金
     * - invest_initial_amount:string 投资初始资金
     * - invest_start_time:string 投资开始时间
     * - invest_end_time:string 投资结束时间
     * - roi_rate:string 收益率
     *
     * @param  Request  $request  request
     * @param  int  $user_code  客户ID
     */
    public function customerHistoryFollowedMasters(Request $request, int $user_code): JsonResponse
    {
        $limit = (int) $request->input('limit', 20);
        $offset = (int) $request->input('offset', 0);
        $result = $this->copyTradingService->customerHistoryFollowedMasters($user_code, $offset, $limit);
        if (! $result['is_succ']) {
            return $this->fail($result['message']);
        }

        return $this->succeed(['data' => $result['data'], 'total_count' => $result['count']], $result['message']);
    }

    #[PathParameter(name: 'master_user_code', description: '高手ID', type: 'integer')]
    #[QueryParameter(name: 'master_type', description: '高手账户类型：1统一交易账户，3赠金账户', type: 'integer', required: false, default: 1)]
    #[Response(type: 'array<int, array{follower_nickname:string,invest_current_amount:string,invest_initial_amount:string,invest_start_time:string}>')]
    /**
     * 客户详情-当前投资人
     *
     * Path:
     * - master_user_code:int 高手ID
     *
     * Query:
     * - master_type:int 高手账户类型（0统一交易账户，3赠金账户；默认 0）
     *
     * Response: array
     *
     * Response[].fields:
     * - follower_nickname:string 跟单者昵称
     * - invest_current_amount:string 投资当前资金
     * - invest_initial_amount:string 投资初始资金
     * - invest_start_time:string 投资开始时间
     *
     * @param  Request  $request  request
     * @param  int  $master_user_code  高手ID
     */
    public function masterCurrentFollowers(MasterCurrentFollowersRequest $request, int $master_user_code): JsonResponse
    {
        $masterType = (int) ($request->input('master_type') ?? TypeTradeAccount::UTA->value);
        $result = $this->copyTradingService->masterCurrentFollowers($master_user_code, $masterType);
        if (! $result['is_succ']) {
            return $this->fail($result['message']);
        }

        return $this->succeed($result['data'], $result['message']);
    }

    #[PathParameter(name: 'master_user_code', description: '高手ID', type: 'integer')]
    #[QueryParameter(name: 'master_type', description: '高手账户类型：1统一交易账户，3赠金账户', type: 'integer', required: false, default: 1)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(type: 'array{data: array<int, array{follower_nickname:string,invest_end_amount:string,invest_initial_amount:string,invest_start_time:string,invest_end_time:string}>, total_count:int}')]
    /**
     * 客户详情-历史投资人
     *
     * Path:
     * - master_user_code:int 高手ID
     *
     * Query:
     * - master_type:int 高手账户类型（0统一交易账户，3赠金账户；默认 0）
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.fields:
     * - data:array 历史投资人列表
     * - total_count:int 总数
     *
     * Response.data[].fields:
     * - follower_nickname:string 跟单者昵称
     * - invest_end_amount:string 投资结束资金
     * - invest_initial_amount:string 投资初始资金
     * - invest_start_time:string 投资开始时间
     * - invest_end_time:string 投资结束时间
     *
     * @param  Request  $request  request
     * @param  int  $master_user_code  高手ID
     */
    public function masterHistoryFollowers(MasterHistoryFollowersRequest $request, int $master_user_code): JsonResponse
    {
        $masterType = (int) ($request->input('master_type') ?? TypeTradeAccount::UTA->value);
        $limit = (int) $request->input('limit', 20);
        $offset = (int) $request->input('offset', 0);
        $result = $this->copyTradingService->masterHistoryFollowers($master_user_code, $masterType, $offset, $limit);
        if (! $result['is_succ']) {
            return $this->fail($result['message']);
        }

        return $this->succeed(['data' => $result['data'], 'total_count' => $result['count']], $result['message']);
    }

    #[QueryParameter(name: 'order_id', description: '订单ID', type: 'string', required: false)]
    #[QueryParameter(name: 'master_user_code', description: '高手ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'follower_user_code', description: '跟单者ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'master_level', description: '高手等级', type: 'string', required: false)]
    #[QueryParameter(name: 'master_trade_account_id', description: '高手交易账号ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'master_trade_account_type', description: '高手账户类型：0统一交易账号，3赠金账户', type: 'integer', required: false)]
    #[QueryParameter(name: 'follower_trade_account_id', description: '跟单者交易账号ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'close_time_start', description: '订单平台时间开始', type: 'string', required: false)]
    #[QueryParameter(name: 'close_time_end', description: '订单平台时间结束', type: 'string', required: false)]
    #[QueryParameter(name: 'profit_time_start', description: '分润时间开始', type: 'string', required: false)]
    #[QueryParameter(name: 'profit_time_end', description: '分润时间结束', type: 'string', required: false)]
    #[QueryParameter(name: 'status', description: '状态：0待分润 1已分润', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(type: 'array{data: array<int, array{order_id:string,follower_user_code:int,master_user_code:int,master_trade_account_id:int,current_profit_amount:string,profit_amount:string,profit_share_amount:string,profit_share_rate:string,receivable_fee_amount:string,actual_fee_amount:string,fee_commission_amount:string,fee_commission_rate:string,fee_commission_time:?string,master_level:string,master_trade_account_type:int,master_trade_account_type_name:string,follower_trade_account_id:int,follower_trade_account_type:int,follower_trade_account_type_name:string,close_time:string,profit_time:string,status:int,status_text:string}>, total_count:int}')]
    /**
     * 分润结算列表
     *
     * Query:
     * - order_id:string 订单ID
     * - master_user_code:int 高手ID
     * - follower_user_code:int 跟单者ID
     * - master_level:string 高手等级
     * - master_trade_account_id:int 高手交易账号ID
     * - master_trade_account_type:int 高手账户类型（0统一交易账号，3赠金账户）
     * - follower_trade_account_id:int 跟单者交易账号ID
     * - close_time_start:string 订单平台时间起
     * - close_time_end:string 订单平台时间止
     * - profit_time_start:string 分润时间起
     * - profit_time_end:string 分润时间止
     * - status:int 状态（0待分润，1已分润）
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - order_id:string 订单ID
     * - follower_user_code:int 跟单者ID
     * - master_user_code:int 高手ID
     * - master_trade_account_id:int 高手交易账号ID
     * - current_profit_amount:string 本次盈利（兼容字段）
     * - profit_amount:string 本次盈利
     * - profit_share_amount:string 分润金额
     * - profit_share_rate:string 分润比例
     * - receivable_fee_amount:string 应收手续费（减免前原始开平仓费，来自平仓快照）
     * - actual_fee_amount:string 实收手续费（减免券减免后实际扣收）
     * - fee_commission_amount:string 手续费分佣金额
     * - fee_commission_rate:string 手续费分佣比例（如10代表10%）
     * - fee_commission_time:?string 手续费分佣时间
     * - master_level:string 高手等级
     * - master_trade_account_type:int 高手账户类型
     * - master_trade_account_type_name:string 高手账户类型文案
     * - follower_trade_account_id:int 跟单者交易账号ID
     * - follower_trade_account_type:int 跟单者账户类型
     * - follower_trade_account_type_name:string 跟单者账户类型文案
     * - close_time:string 订单平台时间
     * - profit_time:string 分润时间
     * - status:int 状态（0待分润，1已分润）
     * - status_text:string 状态文案
     *
     * @param  Request  $request  request
     */
    public function commissionRecords(Request $request): JsonResponse
    {
        $result = $this->copyTradingService->getCommissionRecords(
            [
                'order_id' => $request->input('order_id'),
                'master_user_code' => $request->input('master_user_code'),
                'follower_user_code' => $request->input('follower_user_code'),
                'master_level' => $request->input('master_level'),
                'master_trade_account_id' => $request->input('master_trade_account_id'),
                'master_trade_account_type' => $request->input('master_trade_account_type'),
                'follower_trade_account_id' => $request->input('follower_trade_account_id'),
                'close_time_start' => $request->input('close_time_start'),
                'close_time_end' => $request->input('close_time_end'),
                'profit_time_start' => $request->input('profit_time_start'),
                'profit_time_end' => $request->input('profit_time_end'),
                'status' => $request->input('status'),
            ],
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[QueryParameter(name: 'order_id', description: '订单ID', type: 'string', required: false)]
    #[QueryParameter(name: 'master_user_code', description: '高手ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'follower_user_code', description: '跟单者ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'master_level', description: '高手等级', type: 'string', required: false)]
    #[QueryParameter(name: 'master_trade_account_id', description: '高手交易账号ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'master_trade_account_type', description: '高手账户类型：0统一交易账号，3赠金账户', type: 'integer', required: false)]
    #[QueryParameter(name: 'follower_trade_account_id', description: '跟单者交易账号ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'close_time_start', description: '订单平台时间开始', type: 'string', required: false)]
    #[QueryParameter(name: 'close_time_end', description: '订单平台时间结束', type: 'string', required: false)]
    #[QueryParameter(name: 'profit_time_start', description: '分润时间开始', type: 'string', required: false)]
    #[QueryParameter(name: 'profit_time_end', description: '分润时间结束', type: 'string', required: false)]
    #[QueryParameter(name: 'status', description: '状态：0待分润 1已分润', type: 'integer', required: false)]
    /**
     * 分润结算导出
     */
    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function exportCommissionRecords(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $result = $this->copyTradingService->getCommissionRecords(
            [
                'order_id' => $request->input('order_id'),
                'master_user_code' => $request->input('master_user_code'),
                'follower_user_code' => $request->input('follower_user_code'),
                'master_level' => $request->input('master_level'),
                'master_trade_account_id' => $request->input('master_trade_account_id'),
                'master_trade_account_type' => $request->input('master_trade_account_type'),
                'follower_trade_account_id' => $request->input('follower_trade_account_id'),
                'close_time_start' => $request->input('close_time_start'),
                'close_time_end' => $request->input('close_time_end'),
                'profit_time_start' => $request->input('profit_time_start'),
                'profit_time_end' => $request->input('profit_time_end'),
                'status' => $request->input('status'),
            ],
            -1,
            -1
        );

        $rows = array_map(static function (array $item): array {
            return [
                'order_id' => $item['order_id'] ?? '',
                'follower_user_code' => $item['follower_user_code'] ?? '',
                'master_user_code' => $item['master_user_code'] ?? '',
                'master_trade_account_id' => $item['master_trade_account_id'] ?? '',
                'profit_amount' => $item['profit_amount'] ?? '',
                'profit_share_amount' => $item['profit_share_amount'] ?? '',
                'profit_share_rate' => $item['profit_share_rate'] ?? '',
                'master_level' => $item['master_level'] ?? '',
                'close_time' => $item['close_time'] ?? '',
                'profit_time' => $item['profit_time'] ?? '',
                'status' => $item['status_text'] ?? '',
            ];
        }, $result['data']);

        $headers = trans('copytrading.export.commission_records.headers');
        if (! is_array($headers)) {
            $headers = ['Order ID', 'Follower ID', 'Master ID', 'Master Trade Account ID', 'Profit Amount', 'Profit Share Amount', 'Profit Share Rate', 'Master Level', 'Order Close Time', 'Profit Time', 'Status'];
        }

        return ExportUtil::exportExcelDirect(
            sprintf('copytrading-commission-records-%s', now()->format('YmdHis')),
            $headers,
            $rows,
            $exportExtension
        );
    }

    /**
     * 分页切片
     *
     * @param  array  $rows  原始数据
     * @param  int  $limit  每页条数
     * @param  int  $offset  偏移量
     */
    private function slice(array $rows, int $limit, int $offset): array
    {
        $total = count($rows);
        if ($limit < 0) {
            return ['data' => $rows, 'total_count' => $total];
        }

        return [
            'data' => array_values(array_slice($rows, max(0, $offset), max(1, $limit))),
            'total_count' => $total,
        ];
    }
}
