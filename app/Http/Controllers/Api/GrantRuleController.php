<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\GrantRule\GrantRuleStatusRequest;
use App\Http\Requests\GrantRule\GrantRuleStoreRequest;
use App\Http\Requests\GrantRule\GrantRuleUpdateRequest;
use App\Models\Api\GrantRule;
use App\Services\Api\GrantRuleService;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Group('GrantRule', '投递规则管理')]
class GrantRuleController extends BaseController
{
    private const GRANT_RULE_EVENT_KEY_DESCRIPTION = '自动触发事件；grant_method=2 时必传。取值来自 GET /api/grant_rules/options 返回的 auto_trigger_conditions[].key。不要传 manual，人工投递由 grant_method=1 + user_codes 表示。';

    private const GRANT_RULE_EVENT_PARAMS_DESCRIPTION = '自动投递事件参数；根据 GET /api/grant_rules/options 返回的事件配置传递。支持两种格式：1）推荐传对象，例如 {"amount":"1000"}；2）也可回传模板数组并填 param_value，例如 [{"param_key":"amount","param_value":"1000"}]。invitee_registered 未填写 inviter_user_code 时可不传 event_params。';

    private const GRANT_RULE_EVENT_PARAMS_AMOUNT_DESCRIPTION = '入金金额阈值；event_key=first_deposited 或 deposited 时必传，对应 options 里的 param_key=amount。示例：{"amount":"1000"}。';

    private const GRANT_RULE_EVENT_PARAMS_COUNT_DESCRIPTION = '交易笔数阈值；event_key=open_trade 或 close_trade 时可选，对应 options 里的 param_key=count。';

    private const GRANT_RULE_EVENT_PARAMS_POSITION_SIZE_DESCRIPTION = '交易量阈值；event_key=open_trade 或 close_trade 时可选，对应 param_key=position_size。';

    private const GRANT_RULE_EVENT_PARAMS_LOTS_DESCRIPTION = '手数阈值；event_key=open_trade 或 close_trade 时可选，对应 param_key=lots。';

    private const GRANT_RULE_EVENT_PARAMS_MARGIN_ORDER_DESCRIPTION = '交易金额阈值；event_key=open_trade 或 close_trade 时可选，对应 param_key=margin_order。';

    private const GRANT_RULE_EVENT_PARAMS_PROFIT_DESCRIPTION = '盈利阈值；event_key=close_trade 时可选，对应 param_key=profit。';

    private const GRANT_RULE_EVENT_PARAMS_WEIGHT_DESCRIPTION = '抽奖概率；event_key=lottery 时对应 param_key=weight。前端传百分比（如 30 表示 30%），服务端入库前转换为万分比（30 会保存为 3000），列表回显时再转换回百分比。';

    private const GRANT_RULE_EVENT_PARAMS_INVITER_USER_CODE_DESCRIPTION = '邀请人用户ID数组；event_key=invitee_registered 时可选。填写时会校验每个ID对应的客户是否存在。';

    private const GRANT_RULE_EVENT_PARAMS_RELATION_DESCRIPTION = '事件参数关系；可选，未传时默认 and（入库前自动补齐）。仅 options 返回 relation 选项的事件（如 open_trade/close_trade）传值才有意义，取值必须来自该数组；registered 等单一 and 事件 options 不返回 relation，可不传。';

    public function __construct(private GrantRuleService $grantRuleService) {}

    #[QueryParameter(name: 'keyword', description: '投递任务名关键词', type: 'string', required: false)]
    #[QueryParameter(name: 'target_type', description: '投递物品类型：1=优惠券，2=资金账户，3=模拟账户，4=赠金账户，5=XP，6=Drop', type: 'integer', required: false)]
    #[QueryParameter(name: 'coupon_type', description: '券种类型：1=抹亏券，2=交易返现券，3=手续费减免券，4=通用赠金券，5=限制型赠金券；0=赠金券（筛选通用+限制型）', type: 'integer', required: false)]
    #[QueryParameter(name: 'target', description: 'target_type=1 时为优惠券ID；其他类型为发放数量', type: 'integer', required: false)]
    #[QueryParameter(name: 'grant_method', description: '投递方式：1=人工触发，2=自动触发', type: 'integer', required: false)]
    #[QueryParameter(name: 'status', description: '状态：0=未启用，1=启用，2=作废', type: 'integer', required: false)]
    #[QueryParameter(name: 'operator_name', description: '操作人', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[ScrambleResponse(
        description: '投递规则列表。total_count 是本规则的发放总量；granted_count 是已发放数量，由投递执行方维护，前端只展示，不要提交。target_name：优惠券类型为“券种-券名”，其他类型为目标类型名（如 XP、Drop）。post_count：优惠券类型为已发放张数，其他类型为单次发放数量（target 值）。',
        type: 'array{data: array<int, array{id:int,name:string,target_type:int,target_type_text:string,target:int,target_name:string,grant_method:int,grant_method_text:string,event_key:?string,event_key_text:string,event_params:array,event_params_text:array<int, string>,post_count:int,granted_count:int,total_count:int,start_at:?string,end_at:?string,scheduled_at:?string,status:int,status_text:string,operator_name:string}>, total_count:int}',
        examples: [[
            'data' => [[
                'id' => 2001,
                'name' => '7月亏损用户投递',
                'target_type' => 1,
                'target_type_text' => '优惠券',
                'target' => 1001,
                'target_name' => '7月抹亏券',
                'grant_method' => 1,
                'grant_method_text' => '人工',
                'event_key' => 'manual',
                'event_key_text' => '手动发放',
                'event_params' => ['user_codes' => [10001001, 10001002]],
                'event_params_text' => ['用户ID：10001001,10001002'],
                'post_count' => 8,
                'granted_count' => 8,
                'total_count' => 1000,
                'start_at' => '2026-07-01 00:00:00',
                'end_at' => '2026-07-31 23:59:59',
                'scheduled_at' => null,
                'status' => 1,
                'status_text' => '启用',
                'operator_name' => 'admin',
            ], [
                'id' => 2002,
                'name' => '大转盘04',
                'target_type' => 5,
                'target_type_text' => 'XP',
                'target' => 500,
                'target_name' => 'XP',
                'grant_method' => 2,
                'grant_method_text' => '自动',
                'event_key' => 'lottery',
                'event_key_text' => '抽奖',
                'event_params' => ['weight' => 20],
                'event_params_text' => ['概率：20'],
                'post_count' => 500,
                'granted_count' => 2,
                'total_count' => 2,
                'start_at' => '2026-09-21 00:00:00',
                'end_at' => '2027-09-30 00:00:00',
                'scheduled_at' => null,
                'status' => 1,
                'status_text' => '启用',
                'operator_name' => 'AmyCui',
            ]],
            'total_count' => 1,
        ]]
    )]
    /**
     * 投递规则列表
     */
    public function index(Request $request): JsonResponse
    {
        return $this->succeed($this->grantRuleService->getList(
            [
                'keyword' => trim((string) $request->input('keyword', '')),
                'target_type' => $request->input('target_type'),
                'coupon_type' => $request->input('coupon_type'),
                'target' => $request->input('target'),
                'grant_method' => $request->input('grant_method'),
                'status' => $request->input('status'),
                'operator_name' => trim((string) $request->input('operator_name', '')),
            ],
            max(1, (int) $request->input('limit', 20)),
            max(0, (int) $request->input('offset', 0))
        ));
    }

    #[ScrambleResponse(
        description: '投递规则选项。优惠券目标选项不返回库存字段；每个投递规则单独维护 total_count/granted_count。奖励/积分为目标类型分组，优惠券与其同级。',
        type: 'array{status_options: array<int, array{code:int,label:string}>, grant_method_options: array<int, array{code:int,label:string}>, target_options: array<int, array{key:int|string,value:string,search_key?:string,children:array<int, array{key:int,search_key?:string,value:string,children?:array<int, array{key:int,search_key?:string,value:string,expired_status:int,expired_status_text:string}>}>}>, auto_trigger_conditions: array<int, array{key:string,name:string,event_params:array<int, array{type:string,param_key:string,param_name:string,is_required:bool,is_select_required:bool,param_value:string}>, relation:array<int, string>, is_select_required?:bool}>}'
    )]
    /**
     * 投递规则选项列表
     */
    public function options(): JsonResponse
    {
        return $this->succeed($this->grantRuleService->options());
    }

    #[BodyParameter(name: 'name', description: '投递规则（任务）名称', type: 'string', required: true)]
    #[BodyParameter(name: 'target_type', description: '投递物品类型：1=优惠券，2=资金账户，3=模拟账户，4=赠金账户，5=XP，6=Drop', type: 'integer', required: true)]
    #[BodyParameter(name: 'target', description: 'target_type=1 时为优惠券ID；其他类型为发放数量', type: 'integer', required: true)]
    #[BodyParameter(name: 'grant_method', description: '投递方式：1=人工触发，2=自动触发', type: 'integer', required: true)]
    #[BodyParameter(name: 'total_count', description: '发放总量，0 表示不限量；grant_method=1（人工投递）时可省略，省略或 0 时服务端自动按投递用户数（user_codes 去重后个数）写入，其余场景必填；修改时提交的值不能小于当前 granted_count。granted_count 由投递执行方维护，不需要前端提交', type: 'integer', required: false, example: 1000)]
    #[BodyParameter(name: 'start_at', description: '投递开始时间，格式：Y-m-d H:i:s', type: 'string', required: true)]
    #[BodyParameter(name: 'end_at', description: '投递结束时间，格式：Y-m-d H:i:s', type: 'string', required: true)]
    #[BodyParameter(name: 'user_codes', description: '手工投递用户ID；grant_method=1 时必传。支持数组 [10001,10002] 或逗号分隔字符串 "10001,10002"（中文逗号亦可）', type: 'array', required: false)]
    #[BodyParameter(name: 'event_key', description: self::GRANT_RULE_EVENT_KEY_DESCRIPTION, type: 'string', required: false, example: 'deposited')]
    #[BodyParameter(name: 'event_params', description: self::GRANT_RULE_EVENT_PARAMS_DESCRIPTION, type: 'object', required: false, example: ['amount' => '1000'])]
    #[BodyParameter(name: 'event_params.amount', description: self::GRANT_RULE_EVENT_PARAMS_AMOUNT_DESCRIPTION, type: 'string', required: false, example: '1000')]
    #[BodyParameter(name: 'event_params.count', description: self::GRANT_RULE_EVENT_PARAMS_COUNT_DESCRIPTION, type: 'string', required: false, example: '2')]
    #[BodyParameter(name: 'event_params.position_size', description: self::GRANT_RULE_EVENT_PARAMS_POSITION_SIZE_DESCRIPTION, type: 'string', required: false, example: '1000')]
    #[BodyParameter(name: 'event_params.lots', description: self::GRANT_RULE_EVENT_PARAMS_LOTS_DESCRIPTION, type: 'string', required: false, example: '1')]
    #[BodyParameter(name: 'event_params.margin_order', description: self::GRANT_RULE_EVENT_PARAMS_MARGIN_ORDER_DESCRIPTION, type: 'string', required: false, example: '100')]
    #[BodyParameter(name: 'event_params.profit', description: self::GRANT_RULE_EVENT_PARAMS_PROFIT_DESCRIPTION, type: 'string', required: false, example: '50')]
    #[BodyParameter(name: 'event_params.weight', description: self::GRANT_RULE_EVENT_PARAMS_WEIGHT_DESCRIPTION, type: 'integer', required: false, example: 30)]
    #[BodyParameter(name: 'event_params.inviter_user_code', description: self::GRANT_RULE_EVENT_PARAMS_INVITER_USER_CODE_DESCRIPTION, type: 'array', required: false, example: [10001, 10002])]
    #[BodyParameter(name: 'event_params.relation', description: self::GRANT_RULE_EVENT_PARAMS_RELATION_DESCRIPTION, type: 'string', required: false, example: 'and')]
    #[Endpoint(description: '新增投递规则。total_count 归属于当前规则；grant_method=1（人工投递）时可省略，省略或 0 时服务端自动按投递用户数写入，其余场景必填；granted_count 不需要前端提交，由投递执行方维护。赠金券（coupon_type=4/5，通用与限制型共用同一赠金池）手工投递时会校验用户是否已有有效赠金（未作废、未过期且未用尽的券，或仍有剩余且未过期的赠金记录），已有有效赠金的用户不可重复发放，赠金已用完的用户可再次发放；自动投递赠金券时自动标记 skip_if_has_bonus=true，投递执行方据此跳过已有有效赠金的用户。')]
    #[ScrambleResponse(
        description: '新增投递规则',
        type: 'bool|array{valid_count:int,valid_data:array<int, array{user_code:int,name:string,status:int,status_text:string}>, invalid_count:int,invalid_data:array<int, array{user_code:string,reason:string}>}'
    )]
    /**
     * 新增投递规则
     */
    public function store(GrantRuleStoreRequest $request): JsonResponse
    {
        $result = $this->grantRuleService->create($request->validated());
        if (is_array($result)) {
            return $this->failWithData($result, $this->grantRuleService->userValidationFailureMessage($result));
        }

        return $this->succeed(
            $result,
            trans('public.message.add_success')
        );
    }

    #[PathParameter('grant_rule', description: '投递规则ID', type: 'integer')]
    #[BodyParameter(name: 'name', description: '投递规则（任务）名称', type: 'string', required: true)]
    #[BodyParameter(name: 'target_type', description: '投递物品类型：1=优惠券，2=资金账户，3=模拟账户，4=赠金账户，5=XP，6=Drop', type: 'integer', required: true)]
    #[BodyParameter(name: 'target', description: 'target_type=1 时为优惠券ID；其他类型为发放数量', type: 'integer', required: true)]
    #[BodyParameter(name: 'grant_method', description: '投递方式：1=人工触发，2=自动触发', type: 'integer', required: true)]
    #[BodyParameter(name: 'total_count', description: '发放总量，0 表示不限量；grant_method=1（人工投递）时可省略，省略或 0 时服务端自动按投递用户数（user_codes 去重后个数）写入，其余场景必填；修改时提交的值不能小于当前 granted_count。granted_count 由投递执行方维护，不需要前端提交', type: 'integer', required: false, example: 1000)]
    #[BodyParameter(name: 'start_at', description: '投递开始时间，格式：Y-m-d H:i:s', type: 'string', required: true)]
    #[BodyParameter(name: 'end_at', description: '投递结束时间，格式：Y-m-d H:i:s', type: 'string', required: true)]
    #[BodyParameter(name: 'user_codes', description: '手工投递用户ID；grant_method=1 时必传。支持数组 [10001,10002] 或逗号分隔字符串 "10001,10002"（中文逗号亦可）', type: 'array', required: false)]
    #[BodyParameter(name: 'event_key', description: self::GRANT_RULE_EVENT_KEY_DESCRIPTION, type: 'string', required: false, example: 'deposited')]
    #[BodyParameter(name: 'event_params', description: self::GRANT_RULE_EVENT_PARAMS_DESCRIPTION, type: 'object', required: false, example: ['amount' => '1000'])]
    #[BodyParameter(name: 'event_params.amount', description: self::GRANT_RULE_EVENT_PARAMS_AMOUNT_DESCRIPTION, type: 'string', required: false, example: '1000')]
    #[BodyParameter(name: 'event_params.count', description: self::GRANT_RULE_EVENT_PARAMS_COUNT_DESCRIPTION, type: 'string', required: false, example: '2')]
    #[BodyParameter(name: 'event_params.position_size', description: self::GRANT_RULE_EVENT_PARAMS_POSITION_SIZE_DESCRIPTION, type: 'string', required: false, example: '1000')]
    #[BodyParameter(name: 'event_params.lots', description: self::GRANT_RULE_EVENT_PARAMS_LOTS_DESCRIPTION, type: 'string', required: false, example: '1')]
    #[BodyParameter(name: 'event_params.margin_order', description: self::GRANT_RULE_EVENT_PARAMS_MARGIN_ORDER_DESCRIPTION, type: 'string', required: false, example: '100')]
    #[BodyParameter(name: 'event_params.profit', description: self::GRANT_RULE_EVENT_PARAMS_PROFIT_DESCRIPTION, type: 'string', required: false, example: '50')]
    #[BodyParameter(name: 'event_params.weight', description: self::GRANT_RULE_EVENT_PARAMS_WEIGHT_DESCRIPTION, type: 'integer', required: false, example: 30)]
    #[BodyParameter(name: 'event_params.inviter_user_code', description: self::GRANT_RULE_EVENT_PARAMS_INVITER_USER_CODE_DESCRIPTION, type: 'array', required: false, example: [10001, 10002])]
    #[BodyParameter(name: 'event_params.relation', description: self::GRANT_RULE_EVENT_PARAMS_RELATION_DESCRIPTION, type: 'string', required: false, example: 'and')]
    #[Endpoint(description: '修改投递规则。total_count 归属于当前规则；grant_method=1（人工投递）时可省略，省略或 0 时服务端自动按投递用户数写入，其余场景必填，提交的值不能小于当前 granted_count；granted_count 不需要前端提交，由投递执行方维护。赠金券（coupon_type=4/5，通用与限制型共用同一赠金池）手工投递时会校验用户是否已有有效赠金（未作废、未过期且未用尽的券，或仍有剩余且未过期的赠金记录），已有有效赠金的用户不可重复发放，赠金已用完的用户可再次发放；自动投递赠金券时自动标记 skip_if_has_bonus=true。')]
    #[ScrambleResponse(
        description: '修改投递规则',
        type: 'bool|array{valid_count:int,valid_data:array<int, array{user_code:int,name:string,status:int,status_text:string}>, invalid_count:int,invalid_data:array<int, array{user_code:string,reason:string}>}'
    )]
    /**
     * 修改投递规则
     */
    public function update(GrantRuleUpdateRequest $request, int $grantRule): JsonResponse
    {
        $result = $this->grantRuleService->update($grantRule, $request->validated());
        if ($result === null) {
            return $this->fail(trans('public.message.non_existent'));
        }
        if (is_array($result)) {
            return $this->failWithData($result, $this->grantRuleService->userValidationFailureMessage($result));
        }

        return $this->succeed($result, trans('public.message.upd_success'));
    }

    #[PathParameter('grant_rule', description: '投递规则ID', type: 'integer')]
    #[BodyParameter(name: 'status', description: '状态：1=启用，2=作废', type: 'integer', required: true)]
    #[ScrambleResponse(
        description: '启用/作废投递规则',
        type: 'array{id:int,status:int,status_text:string}'
    )]
    /**
     * 启用/作废投递规则
     */
    public function updateStatus(GrantRuleStatusRequest $request, int $grantRule): JsonResponse
    {
        $status = (int) $request->validated('status');
        $result = $this->grantRuleService->updateStatus($grantRule, $status);
        if ($result === null) {
            return $this->fail(trans('public.message.non_existent'));
        }

        return $this->succeed($result, $status === GrantRule::STATUS_ENABLED
            ? trans('grant_rule.message.enable_success')
            : trans('grant_rule.message.void_success'));
    }

    #[BodyParameter(name: 'user_codes', description: '用户ID列表，前端以中文逗号或英文逗号分隔', type: 'string', required: true)]
    #[ScrambleResponse(
        description: '验证多个用户 Mock 返回',
        type: 'array{valid_count:int,valid_data:array<int, array{user_code:int,name:string,status:int,status_text:string}>, invalid_count:int,invalid_data:array<int, array{user_code:string,reason:string}>}'
    )]
    /**
     * 手工投递用户校验
     */
    public function validateUsers(Request $request): JsonResponse
    {
        return $this->succeed(
            $this->grantRuleService->validateUsers((string) $request->input('user_codes', ''))
        );
    }

    #[ScrambleResponse(
        description: '手工投递用户导入模板下载',
        type: 'binary',
        examples: [[
            'file_name' => 'grant-rule-user-template.xlsx',
            'export_ext' => 'xlsx',
        ]]
    )]
    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    /**
     * 手工投递用户模板下载
     */
    public function template(Request $request): BinaryFileResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        return ExportUtil::exportExcelDirect(
            sprintf('grant-rule-user-template-%s', now()->format('YmdHis')),
            [trans('grant_rule.template.user_code_header')],
            [
                ['10001001'],
                ['10001002'],
            ],
            $exportExtension
        );
    }

    #[BodyParameter(name: 'file', description: '上传的 Excel 文件，form-data 文件字段名固定为 file', type: 'file', required: true)]
    #[ScrambleResponse(
        description: '批量导入用户',
        type: 'array{file_name:string,valid_count:int,valid_data:array<int, array{user_code:int,name:string,status:int,status_text:string}>, invalid_count:int,invalid_data:array<int, array{user_code:string,reason:string}>}'
    )]
    /**
     * 手工投递用户批量导入
     */
    public function importUsers(Request $request): JsonResponse
    {
        $result = $this->grantRuleService->importUsers($request->file('file'));
        if (($result['invalid_count'] ?? 0) > 0) {
            return $this->failWithData($result, $this->grantRuleService->userValidationFailureMessage($result));
        }

        return $this->succeed($result);
    }

    #[QueryParameter(name: 'keyword', description: '投递任务名/投递物关键词', type: 'string', required: false)]
    #[QueryParameter(name: 'rule_name', description: '投递任务名关键词', type: 'string', required: false)]
    #[QueryParameter(name: 'rule_id', description: '投递规则ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'user_code', description: '用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'target_type', description: '投递物品类型：1=优惠券，2=资金账户，3=模拟账户，4=赠金账户，5=XP，6=Drop', type: 'integer', required: false)]
    #[QueryParameter(name: 'coupon_type', description: '券种类型：1=抹亏券，2=交易返现券，3=手续费减免券，4=通用赠金券，5=限制型赠金券；0=赠金券（筛选通用+限制型）', type: 'integer', required: false)]
    #[QueryParameter(name: 'target', description: 'target_type=1 时为优惠券ID；其他类型为发放数量', type: 'integer', required: false)]
    #[QueryParameter(name: 'status', description: '优惠券实例状态：10=未领取，20=未激活，30=可使用，40=不可使用，50=已过期，60=已作废', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[ScrambleResponse(
        description: '投递用户记录',
        type: 'array{data: array<int, array{id:int,rule_id:int,rule_name:string,target_type:int,target:int,target_name:string,user_code:int,received_at:?string,target_start_at:?string,target_end_at:?string,status:int,status_text:string,operator_name:string}>, total_count:int}'
    )]
    /**
     * 投递用户记录
     */
    public function records(Request $request): JsonResponse
    {
        return $this->succeed($this->grantRuleService->records(
            [
                'keyword' => trim((string) $request->input('keyword', '')),
                'rule_name' => trim((string) $request->input('rule_name', '')),
                'rule_id' => $request->input('rule_id'),
                'user_code' => $request->input('user_code'),
                'target_type' => $request->input('target_type'),
                'coupon_type' => $request->input('coupon_type'),
                'target' => $request->input('target'),
                'status' => $request->input('status'),
            ],
            max(1, (int) $request->input('limit', 20)),
            max(0, (int) $request->input('offset', 0))
        ));
    }

    private function mockGrantRules(): array
    {
        return [
            [
                'id' => 2001,
                'name' => '6月亏损用户手工投递',
                'target_type' => 1,
                'target_type_text' => '优惠券',
                'target' => 1001,
                'target_name' => '抹亏券-6月亏损返还抹亏券',
                'grant_method' => 1,
                'grant_method_text' => '人工',
                'event_key' => 'manual',
                'event_key_text' => '手动发放',
                'event_params' => ['user_codes' => [10001001, 10001002, 10001003]],
                'event_params_text' => ['用户ID：10001001,10001002,10001003'],
                'post_count' => 128,
                'granted_count' => 128,
                'total_count' => 1000,
                'start_at' => '2026-06-01 00:00:00',
                'end_at' => '2026-06-30 23:59:59',
                'scheduled_at' => null,
                'status' => 1,
                'status_text' => '启用',
                'operator_id' => 90001,
                'operator_name' => 'Mock Admin',
            ],
            [
                'id' => 2002,
                'name' => '新客入金自动投递',
                'target_type' => 1,
                'target_type_text' => '优惠券',
                'target' => 1002,
                'target_name' => '抹亏券-新客入金抹亏券',
                'grant_method' => 2,
                'grant_method_text' => '自动',
                'event_key' => 'deposit_success',
                'event_key_text' => '入金成功',
                'event_params' => [
                    'amount_compare' => 'gte',
                    'amount' => '1000',
                ],
                'event_params_text' => ['入金金额大于等于 1000'],
                'post_count' => 0,
                'granted_count' => 0,
                'total_count' => 500,
                'start_at' => '2026-07-01 00:00:00',
                'end_at' => '2026-07-31 23:59:59',
                'scheduled_at' => '2026-07-01 09:00:00',
                'status' => 0,
                'status_text' => '未启用',
                'operator_id' => 90002,
                'operator_name' => 'Mock Operator',
            ],
            [
                'id' => 2003,
                'name' => '5月活动用户投递',
                'target_type' => 1,
                'target_type_text' => '优惠券',
                'target' => 1003,
                'target_name' => '抹亏券-5月活动抹亏券',
                'grant_method' => 1,
                'grant_method_text' => '人工',
                'event_key' => 'manual',
                'event_key_text' => '手动发放',
                'event_params' => ['user_codes' => [10001004, 10001005]],
                'event_params_text' => ['用户ID：10001004,10001005'],
                'post_count' => 800,
                'granted_count' => 800,
                'total_count' => 800,
                'start_at' => '2026-05-01 00:00:00',
                'end_at' => '2026-05-31 23:59:59',
                'scheduled_at' => null,
                'status' => 2,
                'status_text' => '作废',
                'operator_id' => 90001,
                'operator_name' => 'Mock Admin',
            ],
        ];
    }

    private function mockGrantRulePayload(int $id): array
    {
        return [
            'id' => $id,
            'name' => 'Mock 投递规则',
            'target_type' => 1,
            'target' => 1001,
            'target_name' => '抹亏券-6月亏损返还抹亏券',
            'grant_method' => 1,
            'grant_method_text' => '人工',
            'event_key' => 'manual',
            'event_params' => ['user_codes' => [10001001, 10001002]],
            'status' => 0,
            'status_text' => '未启用',
            'start_at' => '2026-06-01 00:00:00',
            'end_at' => '2026-06-30 23:59:59',
        ];
    }

    private function mockUserValidationResult(): array
    {
        return [
            'valid_count' => 3,
            'valid_data' => [
                ['user_code' => 10001001, 'name' => 'Mock User A', 'status' => 1, 'status_text' => '正常'],
                ['user_code' => 10001002, 'name' => 'Mock User B', 'status' => 1, 'status_text' => '正常'],
                ['user_code' => 10001003, 'name' => 'Mock User C', 'status' => 1, 'status_text' => '正常'],
            ],
            'invalid_count' => 2,
            'invalid_data' => [
                ['user_code' => '99999999', 'reason' => '用户不存在'],
                ['user_code' => 'abc', 'reason' => '用户ID格式错误'],
            ],
        ];
    }

    private function mockGrantRecords(): array
    {
        return [
            [
                'id' => 7001,
                'rule_id' => 2001,
                'rule_name' => '6月亏损用户手工投递',
                'target_type' => 1,
                'target' => 1001,
                'target_name' => '抹亏券-6月亏损返还抹亏券',
                'user_code' => 10001001,
                'received_at' => '2026-06-15 10:20:00',
                'target_start_at' => '2026-06-15 10:20:00',
                'target_end_at' => '2026-06-30 23:59:59',
                'operator_id' => 90001,
                'operator_name' => 'Mock Admin',
            ],
            [
                'id' => 7002,
                'rule_id' => 2001,
                'rule_name' => '6月亏损用户手工投递',
                'target_type' => 1,
                'target' => 1001,
                'target_name' => '抹亏券-6月亏损返还抹亏券',
                'user_code' => 10001002,
                'received_at' => '2026-06-16 11:05:13',
                'target_start_at' => '2026-06-16 11:05:13',
                'target_end_at' => '2026-06-30 23:59:59',
                'operator_id' => 90001,
                'operator_name' => 'Mock Admin',
            ],
            [
                'id' => 7003,
                'rule_id' => 2002,
                'rule_name' => '新客入金自动投递',
                'target_type' => 1,
                'target' => 1002,
                'target_name' => '抹亏券-新客入金抹亏券',
                'user_code' => 10001003,
                'received_at' => '2026-07-01 09:30:00',
                'target_start_at' => '2026-07-01 09:30:00',
                'target_end_at' => '2026-07-31 23:59:59',
                'operator_id' => 0,
                'operator_name' => 'System',
            ],
        ];
    }

    private function grantRuleStatusText(int $status): string
    {
        return match ($status) {
            1 => '启用',
            2 => '作废',
            default => '未启用',
        };
    }
}
