<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\IbCommissionRule\IbCommissionRuleListRequest;
use App\Http\Requests\IbCommissionRule\IbCommissionRuleReviewRequest;
use App\Http\Requests\IbCommissionRule\IbCommissionRuleStoreRequest;
use App\Http\Requests\IbCommissionRule\IbCommissionRuleUpdateRequest;
use App\Services\Api\IbCommissionRuleService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('IB Commission Rule', 'IB佣金规则（ib_commission_rule / ib_commission_rule_version / ib_commission_rule_detail）')]
class IbCommissionRuleController extends BaseController
{
    public function __construct(private IbCommissionRuleService $ibCommissionRuleService) {}

    #[QueryParameter(name: 'name', description: '佣金规则名称', type: 'string', required: false)]
    #[QueryParameter(name: 'level_name', description: '关联等级名称', type: 'string', required: false)]
    #[QueryParameter(name: 'is_effective', description: '是否生效：0=否，1=是', type: 'integer', required: false)]
    #[QueryParameter(name: 'review_status', description: '审核状态：0=草稿，1=待审核，2=审核通过，3=已驳回，4=已停用', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: "佣金规则列表（分页，按保底佣金比例升序；比例相同时按ID倒序）\n- review_status: 审核状态（0=草稿，1=待审核，2=审核通过，3=已驳回，4=已停用）\n- effective_status: 生效状态（0=否，1=是）\n- can_delete: 是否可删除（1是/0否）",
        type: 'array{data: array<int, array{id:int,name:string,locale_configs:array<int, array{lang:string,name:string}>,level_name:string,base_commission_rate:string,promotion_commission_rate:string,created_at:string,reviewed_at:?string,review_status:int,review_status_text:string,effective_status:int,effective_status_text:string,rejection_reason:?string,can_delete:int}>, total_count:int}',
        examples: [[
            'data' => [
                [
                    'id' => 1,
                    'name' => 'XXXX规则',
                    'locale_configs' => [
                        ['lang' => 'zh_CN', 'name' => 'XXXX规则'],
                        ['lang' => 'en_US', 'name' => 'Rule XXXX'],
                    ],
                    'level_name' => '黄金',
                    'base_commission_rate' => '2.5',
                    'promotion_commission_rate' => '0',
                    'created_at' => '2026-03-26 23:55:48',
                    'reviewed_at' => '2026-03-27 23:55:48',
                    'review_status' => 1,
                    'review_status_text' => '待审核',
                    'effective_status' => 0,
                    'effective_status_text' => '否',
                    'rejection_reason' => null,
                    'can_delete' => 1,
                ],
            ],
            'total_count' => 1,
        ]]
    )]
    /**
     * 获取佣金规则列表
     *
     * Query:
     * - name:string 佣金规则名称
     * - level_name:string 关联等级名称
     * - effective_status:int 生效状态（0否，1是）
     * - review_status:int 审核状态（0草稿，1待审核，2审核通过，3已驳回，4已停用）
     * - limit:int 每页条数
     * - offset:int 分页偏移量
 *
 * 列表默认按 base_commission_rate 升序排序；相同比例时按 id 倒序排序。
     *
     * Response.data[].fields:
     * - id:int 佣金规则ID
     * - name:string 当前登录语言下的佣金规则名称
     * - locale_configs:array 多语言配置[{lang,name}]
     * - level_name:string 关联等级名称
     * - base_commission_rate:string 保底佣金比例（百分比）
     * - promotion_commission_rate:string 推广佣金比例（百分比），默认0
     * - created_at:string 创建时间
     * - reviewed_at:?string 审核通过时间
     * - review_status:int 审核状态（0草稿，1待审核，2审核通过，3已驳回，4已停用）
     * - review_status_text:string 审核状态文案
     * - effective_status:int 生效状态（0否，1是）
     * - effective_status_text:string 生效状态文案
     * - rejection_reason:?string 拒绝原因
     * - can_delete:int 是否可删除（1是/0否）
     *
     * @param  IbCommissionRuleListRequest  $request  request
     */
    public function index(IbCommissionRuleListRequest $request): JsonResponse
    {
        $result = $this->ibCommissionRuleService->getList(
            [
                'name' => $request->input('name'),
                'level_name' => $request->input('level_name'),
                'is_effective' => $request->input('is_effective'),
                'review_status' => $request->input('review_status'),
            ],
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[PathParameter(name: 'rule_id', description: '佣金规则ID（路由参数，必填）', type: 'integer', required: true)]
    #[QueryParameter(name: 'source', description: '品种代码，选填', type: 'string', required: false)]
    #[Response(
        description: "佣金规则详情列表\n- 返回当前规则名称、当前语言以及全量品种列表\n- details 出参中的 commission_amount / commission_fee_rate 始终返回数值字符串\n- 入库规则为二选一：新增/修改时每条 details 必须且仅能传 commission_amount 或 commission_fee_rate 之一",
        type: 'array{
            current_lang:string,
            rule_name:string,
            base_commission_rate:string,
            promotion_commission_rate:string,
            data: array<int, array{
                source:string,
                source_name:string,
                is_source_deleted:bool,
                settlement_unit:?string,
                commission_amount:string,
                commission_fee_rate:string,
                is_support_open_close_fee:bool,
                direction_details: array{
                    buy: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string},
                    sell: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string}
                }
            }>
        }',
        examples: [[
            'current_lang' => 'zh_CN',
            'rule_name' => 'XXXX规则',
            'base_commission_rate' => '2.5',
            'promotion_commission_rate' => '0',
            'data' => [
                [
                    'source' => 'AUDCAD',
                    'source_name' => '澳元/加元',
                    'is_source_deleted' => false,
                    'settlement_unit' => '100000',
                    'commission_amount' => '10',
                    'commission_fee_rate' => '2.5',
                    'is_support_open_close_fee' => true,
                    'direction_details' => [
                        'buy' => [
                            'open_fee_rate' => '-0.000022',
                            'open_fee' => '-2.2',
                            'close_fee_rate' => '-0.000022',
                            'close_fee' => '-2.2',
                        ],
                        'sell' => [
                            'open_fee_rate' => '-0.000022',
                            'open_fee' => '-2.2',
                            'close_fee_rate' => '-0.000022',
                            'close_fee' => '-2.2',
                        ],
                    ],
                ],
                [
                    'source' => 'BTCUSD',
                    'source_name' => 'BTCUSD',
                    'is_source_deleted' => true,
                    'settlement_unit' => null,
                    'commission_amount' => '',
                    'commission_fee_rate' => '',
                    'is_support_open_close_fee' => false,
                    'direction_details' => [
                        'buy' => [
                            'open_fee_rate' => null,
                            'open_fee' => null,
                            'close_fee_rate' => null,
                            'close_fee' => null,
                        ],
                        'sell' => [
                            'open_fee_rate' => null,
                            'open_fee' => null,
                            'close_fee_rate' => null,
                            'close_fee' => null,
                        ],
                    ],
                ],
            ],
        ]]
    )]
    /**
     * 获取佣金规则详情列表
     *
     * Path:
     * - rule_id:int 佣金规则ID（路由参数，必填）
     *
     * Query:
     * - source:string 品种代码，选填
     *
     * Response.fields:
     * - current_lang:string 当前语言
     * - rule_name:string 当前规则名称
     * - base_commission_rate:string 保底佣金比例（百分比）
     * - promotion_commission_rate:string 推广佣金比例（百分比），默认0
     * - data:array 全量品种列表
     * - data[].source:string 品种代码
     * - data[].source_name:string 当前语言下的品种名称
     * - data[].is_source_deleted:bool 品种是否已从 ts_source 删除（true=已删除）
     * - data[].settlement_unit:?string 结算单位；未配置时为空
     * - data[].commission_amount:string 分佣金额（未配置时返回空字符串）
     * - data[].commission_fee_rate:string 分佣比例（读取 ib_commission_rule_detail.commission_fee_rate，未配置时返回空字符串）
     * - data[].commission_amount / data[].commission_fee_rate: 二选一入库后返回（未选字段按 0 返回）
     * - data[].is_support_open_close_fee:bool 是否支持开平仓费用（开/平仓费状态）
     * - data[].direction_details.buy.open_fee_rate:?string 买开仓费率（取 ts_source.config.open_fee_buy_rate）
     * - data[].direction_details.buy.open_fee:?string 买开仓费用（settlement_unit * 买开仓费率）
     * - data[].direction_details.buy.close_fee_rate:?string 买平仓费率（取 ts_source.config.close_fee_rate，买卖共用）
     * - data[].direction_details.buy.close_fee:?string 买平仓费用（settlement_unit * 平仓费率）
     * - data[].direction_details.sell.open_fee_rate:?string 卖开仓费率（取 ts_source.config.open_fee_sell_rate）
     * - data[].direction_details.sell.open_fee:?string 卖开仓费用（settlement_unit * 卖开仓费率）
     * - data[].direction_details.sell.close_fee_rate:?string 卖平仓费率（取 ts_source.config.close_fee_rate，买卖共用）
     * - data[].direction_details.sell.close_fee:?string 卖平仓费用（settlement_unit * 平仓费率）
     *
     * @param  int  $rule_id  佣金规则ID（路由参数）
     */
    public function detail(int $rule_id): JsonResponse
    {
        return $this->succeed($this->ibCommissionRuleService->getDetail(
            $rule_id,
            request()->input('source')
        ));
    }

    #[BodyParameter(name: 'locale_configs', description: '多语言配置[{lang,name}]，必填', type: 'array', required: true)]
    #[BodyParameter(name: 'base_commission_rate', description: '保底佣金比例（百分比），必填；不允许与其他佣金规则当前生效或待审核版本重复', type: 'number', required: true)]
    #[BodyParameter(name: 'promotion_commission_rate', description: '推广佣金比例（百分比），选填，默认0', type: 'number', required: false)]
    #[BodyParameter(name: 'details', description: '佣金规则明细数组，必填，必须包含所有上架品种', type: 'array', required: true)]
    #[BodyParameter(name: 'details.*.source', description: '品种代码，必填', type: 'string', required: true)]
    #[BodyParameter(name: 'details.*.position_size_threshold', description: '结算单位，必填，>=0', type: 'number', required: true)]
    #[BodyParameter(name: 'details.*.commission_amount', description: '分佣金额，选填；与 commission_fee_rate 二选一且仅能传一个，>=0', type: 'number', required: false)]
    #[BodyParameter(name: 'details.*.commission_fee_rate', description: '分佣比例（百分比），选填；与 commission_amount 二选一且仅能传一个，>=0', type: 'number', required: false)]
    /**
     * 新增佣金规则
     *
     * Body:
     * - locale_configs:array 多语言配置[{lang,name}]，必填
     * - base_commission_rate:number 保底佣金比例（百分比），必填；不允许与其他佣金规则当前生效或待审核版本重复
     * - promotion_commission_rate:number 推广佣金比例（百分比），选填，默认0
     * - details:array 佣金规则明细数组，必填，必须包含所有上架品种
     * - details[].source:string 品种代码，必填
     * - details[].position_size_threshold:number 结算单位，必填，>=0
     * - details[].commission_amount:number 分佣金额，选填，>=0
     * - details[].commission_fee_rate:number 分佣比例（百分比），选填，>=0
     * - details[].commission_amount / details[].commission_fee_rate：每条明细必须且仅能传一个
     *
     * 校验规则：
     * - details 必须覆盖所有上架品种，否则返回错误"有部分上架品种未设置"
     * - 未传入的下架品种将自动补全（默认值为 0）
     *
     * @param  IbCommissionRuleStoreRequest  $request  request
     */
    public function store(IbCommissionRuleStoreRequest $request): JsonResponse
    {
        $this->ibCommissionRuleService->store($request->validated());

        return $this->succeed();
    }

    #[PathParameter(name: 'rule_id', description: '佣金规则ID（路由参数，必填）', type: 'integer', required: true)]
    #[BodyParameter(name: 'locale_configs', description: '多语言配置[{lang,name}]，必填', type: 'array', required: true)]
    #[BodyParameter(name: 'base_commission_rate', description: '保底佣金比例（百分比），必填；不允许与其他佣金规则当前生效或待审核版本重复', type: 'number', required: true)]
    #[BodyParameter(name: 'promotion_commission_rate', description: '推广佣金比例（百分比），选填，默认0', type: 'number', required: false)]
    #[BodyParameter(name: 'details', description: '佣金规则明细数组，必填，必须包含所有上架品种', type: 'array', required: true)]
    #[BodyParameter(name: 'details.*.source', description: '品种代码，必填', type: 'string', required: true)]
    #[BodyParameter(name: 'details.*.position_size_threshold', description: '结算单位，必填，>=0', type: 'number', required: true)]
    #[BodyParameter(name: 'details.*.commission_amount', description: '分佣金额，选填；与 commission_fee_rate 二选一且仅能传一个，>=0', type: 'number', required: false)]
    #[BodyParameter(name: 'details.*.commission_fee_rate', description: '分佣比例（百分比），选填；与 commission_amount 二选一且仅能传一个，>=0', type: 'number', required: false)]
    /**
     * 修改佣金规则
     *
     * Path:
     * - rule_id:int 佣金规则ID（路由参数，必填）
     *
     * Body:
     * - locale_configs:array 多语言配置[{lang,name}]，必填
     * - base_commission_rate:number 保底佣金比例（百分比），必填；不允许与其他佣金规则当前生效或待审核版本重复
     * - promotion_commission_rate:number 推广佣金比例（百分比），选填，默认0
     * - details:array 佣金规则明细数组，必填，必须包含所有上架品种
     * - details[].source:string 品种代码，必填
     * - details[].position_size_threshold:number 结算单位，必填，>=0
     * - details[].commission_amount:number 分佣金额，选填，>=0
     * - details[].commission_fee_rate:number 分佣比例（百分比），选填，>=0
     * - details[].commission_amount / details[].commission_fee_rate：每条明细必须且仅能传一个
     *
     * 校验规则：
     * - details 必须覆盖所有上架品种，否则返回错误"有部分上架品种未设置"
     * - 未传入的下架品种将自动补全（默认值为 0）
     *
     * @param  IbCommissionRuleUpdateRequest  $request  request
     * @param  int  $rule_id  佣金规则ID（路由参数）
     */
    public function update(IbCommissionRuleUpdateRequest $request, int $rule_id): JsonResponse
    {
        $this->ibCommissionRuleService->update($rule_id, $request->validated());

        return $this->succeed();
    }

    #[PathParameter(name: 'rule_id', description: '佣金规则ID（路由参数，必填）', type: 'integer', required: true)]
    #[BodyParameter(name: 'action', description: '审核动作：1=通过，2=拒绝，必填', type: 'integer', required: true)]
    #[BodyParameter(name: 'rejection_reason', description: '拒绝原因；action=2 时传', type: 'string', required: false)]
    /**
     * 审核佣金规则
     *
     * Path:
     * - rule_id:int 佣金规则ID（路由参数，必填）
     *
     * Body:
     * - action:int 审核动作（1通过，2拒绝），必填
     * - rejection_reason:?string 拒绝原因；action=2 时传
     *
     * @param  IbCommissionRuleReviewRequest  $request  request
     * @param  int  $rule_id  佣金规则ID（路由参数）
     */
    public function review(IbCommissionRuleReviewRequest $request, int $rule_id): JsonResponse
    {
        $this->ibCommissionRuleService->review(
            $rule_id,
            (int) $request->input('action'),
            $request->input('rejection_reason')
        );

        return $this->succeed();
    }

    #[PathParameter(name: 'rule_id', description: '佣金规则ID（路由参数，必填）', type: 'integer', required: true)]
    /**
     * 删除佣金规则
     *
     * Path:
     * - rule_id:int 佣金规则ID（路由参数，必填）
     *
     * @param  int  $rule_id  佣金规则ID（路由参数）
     */
    public function destroy(int $rule_id): JsonResponse
    {
        $this->ibCommissionRuleService->destroy($rule_id);

        return $this->succeed();
    }
}
