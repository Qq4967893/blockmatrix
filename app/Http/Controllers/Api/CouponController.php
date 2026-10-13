<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Coupon\CouponStatusRequest;
use App\Http\Requests\Coupon\CouponStoreRequest;
use App\Http\Requests\Coupon\CouponUpdateRequest;
use App\Services\Api\CouponService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Coupon', '优惠券管理')]
class CouponController extends BaseController
{
    public function __construct(private CouponService $couponService) {}

    #[QueryParameter(name: 'keyword', description: '优惠券活动名（对内/对外）关键词', type: 'string', required: false)]
    #[QueryParameter(name: 'coupon_type', description: '优惠券类型：1=抹亏券，2=交易返现券，3=手续费减免券，4=通用赠金券，5=限制型赠金券；0=赠金券（筛选通用+限制型）', type: 'integer', required: false)]
    #[QueryParameter(name: 'status', description: '状态：0=未启用，1=启用，2=作废，3=停用', type: 'integer', required: false)]
    #[QueryParameter(name: 'expired_status', description: '过期状态：0=未过期，1=已过期', type: 'integer', required: false)]
    #[QueryParameter(name: 'operator_name', description: '操作人', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[ScrambleResponse(
        description: '优惠券列表。优惠券自身不返回发放总量和已发放数量；库存信息以投递规则列表的 total_count/granted_count 为准。',
        type: 'array{data: array<int, array{id:int,name_internal:string,name_external:array,name_external_text:string,locale_configs:array,coupon_type:int,coupon_type_text:string,rule_name:array,rule_name_text:string,start_at:?string,end_at:?string,config:array,active_config:?array,status:int,status_text:string,expired_status:int,expired_status_text:string,operator_id:int,operator_name:string}>, total_count:int}',
        examples: [[
            'data' => [[
                'id' => 1001,
                'name_internal' => '7月抹亏券',
                'name_external' => ['zh_CN' => '7月抹亏券', 'en_US' => 'July Loss Offset Coupon'],
                'name_external_text' => '7月抹亏券',
                'locale_configs' => [[
                    'lang' => 'zh_CN',
                    'name' => '7月抹亏券',
                    'description' => '7月交易亏损返还',
                ]],
                'coupon_type' => 1,
                'coupon_type_text' => '抹亏券',
                'rule_name' => ['zh_CN' => '亏损返还规则'],
                'rule_name_text' => '亏损返还规则',
                'start_at' => '2026-07-01 00:00:00',
                'end_at' => '2026-07-31 23:59:59',
                'config' => [
                    'discount_method' => 1,
                    'discount_rate' => 20,
                    'discount_cap' => 100,
                ],
                'active_config' => null,
                'status' => 1,
                'status_text' => '启用',
                'expired_status' => 0,
                'expired_status_text' => '未过期',
                'operator_id' => 90001,
                'operator_name' => 'admin',
            ]],
            'total_count' => 1,
        ]]
    )]
    /**
     * 优惠券列表
     */
    public function index(Request $request): JsonResponse
    {
        return $this->succeed($this->couponService->getList(
            [
                'keyword' => trim((string) $request->input('keyword', '')),
                'coupon_type' => $request->input('coupon_type'),
                'status' => $request->input('status'),
                'expired_status' => $request->input('expired_status'),
                'operator_name' => trim((string) $request->input('operator_name', '')),
            ],
            max(1, (int) $request->input('limit', 20)),
            max(0, (int) $request->input('offset', 0))
        ));
    }

    #[ScrambleResponse(
        description: '优惠券选项',
        type: 'array{status_options: array<int, array{code:int,label:string}>, expired_status_options: array<int, array{code:int,label:string}>, coupon_type_options: array<int, array{code:int,label:string,children?:array<int,array{code:int,label:string}>}>, redeem_status_options: array<int, array{code:int,label:string}>, trade_account_type_options: array<int, array{code:int,label:string}>, active_config_options: array{relation:array<int,string>,variable:array<int,array{variable:string,variable_label:string,description:string,type:int,type_text:string,is_required:bool,is_select_required:bool,options?:array<int,array{value:int|string,label:string}>}>}, type_detail_options: array<int, array{code:int,label:string,relation:array<int,string>,variable:array<int,array{variable:string,variable_label:string,variable_label_note?:string,description:string,type:int,type_text:string,is_required:bool,is_select_required:bool,options?:array<int,array{value:int|string,label:string}>,children?:array<int,array{variable:string,variable_label:string,variable_label_note?:string,description:string,type:int,type_text:string,is_required:bool,is_select_required:bool,options?:array<int,array{value:int|string,label:string}>}>}>}>}'
    )]
    /**
     * 优惠券选项列表
     */
    public function options(): JsonResponse
    {
        return $this->succeed($this->couponService->options());
    }

    #[BodyParameter(name: 'name_internal', description: '优惠券活动名（对内）', type: 'string', required: true)]
    #[BodyParameter(name: 'name_external', description: '优惠券活动名（对外多语言），示例：{"zh_CN":"抹亏券","en_US":"Loss Offset Coupon"}', type: 'object', required: true)]
    #[BodyParameter(name: 'coupon_type', description: '优惠券类型：1=抹亏券，2=交易返现券，3=手续费减免券，4=通用赠金券，5=逐仓赠金券；0 为 options 接口的赠金券虚拟分组码，仅前端交互用，不可提交', type: 'integer', required: true)]
    #[BodyParameter(name: 'start_at', description: '有效期开始时间，格式：Y-m-d H:i:s', type: 'string', required: true)]
    #[BodyParameter(name: 'end_at', description: '有效期结束时间，格式：Y-m-d H:i:s', type: 'string', required: true)]
    #[BodyParameter(name: 'rule_name', description: '规则名称（多语言），示例：{"zh_CN":"亏损返还规则","en_US":"Loss Rebate Rule"}', type: 'object', required: true)]
    #[BodyParameter(name: 'config', description: '券种配置；允许字段、选项和必填状态来自 GET /api/coupons/options 的 type_detail_options。比例类字段（discount_rate/deposited_rate，及解锁单位=交易手续费时的 unlock_unit_amount）传百分点：50 表示 50%，返回同样为百分点', type: 'object', required: true)]
    #[BodyParameter(name: 'active_config', description: '激活配置；null 表示关闭，开启时传 {"unit_type":"lots","unit_size":2,"expire_days":7,"source":["XAUUSD"]}', type: 'object|null', required: false)]
    #[Endpoint(description: '新增优惠券。优惠券不包含发放总量和已发放数量；发放总量在新增投递规则时通过 total_count 配置。')]
    #[ScrambleResponse(
        description: '新增优惠券',
        type: 'bool'
    )]
    /**
     * 新增优惠券
     */
    public function store(CouponStoreRequest $request): JsonResponse
    {
        return $this->succeed(
            $this->couponService->create($request->validated()),
            trans('public.message.add_success')
        );
    }

    #[PathParameter('coupon', description: '优惠券ID', type: 'integer')]
    #[BodyParameter(name: 'name_internal', description: '优惠券活动名（对内）', type: 'string', required: true)]
    #[BodyParameter(name: 'name_external', description: '优惠券活动名（对外多语言）', type: 'object', required: true)]
    #[BodyParameter(name: 'coupon_type', description: '优惠券类型：1=抹亏券，2=交易返现券，3=手续费减免券，4=通用赠金券，5=逐仓赠金券；0 为 options 接口的赠金券虚拟分组码，仅前端交互用，不可提交', type: 'integer', required: true)]
    #[BodyParameter(name: 'start_at', description: '有效期开始时间，格式：Y-m-d H:i:s', type: 'string', required: true)]
    #[BodyParameter(name: 'end_at', description: '有效期结束时间，格式：Y-m-d H:i:s', type: 'string', required: true)]
    #[BodyParameter(name: 'rule_name', description: '规则名称（多语言）', type: 'object', required: true)]
    #[BodyParameter(name: 'config', description: '券种配置；字段与新增接口一致，跟随 GET /api/coupons/options 的 type_detail_options。比例类字段传百分点：50 表示 50%，返回同样为百分点', type: 'object', required: true)]
    #[BodyParameter(name: 'active_config', description: '激活配置；null 表示关闭，字段与新增接口一致', type: 'object|null', required: false)]
    #[Endpoint(description: '修改优惠券。优惠券不包含发放总量和已发放数量；发放总量在修改投递规则时通过 total_count 配置。')]
    #[ScrambleResponse(
        description: '修改优惠券',
        type: 'bool'
    )]
    /**
     * 修改优惠券
     */
    public function update(CouponUpdateRequest $request, int $coupon): JsonResponse
    {
        $result = $this->couponService->update($coupon, $request->validated());
        if ($result === null) {
            return $this->fail(trans('public.message.non_existent'));
        }

        return $this->succeed($result, trans('public.message.upd_success'));
    }

    #[PathParameter('coupon', description: '优惠券ID', type: 'integer')]
    #[BodyParameter(name: 'status', description: '状态：1=启用，2=作废，3=停用', type: 'integer', required: true)]
    #[ScrambleResponse(
        description: '启用/停用/作废优惠券',
        type: 'array{id:int,status:int,status_text:string}'
    )]
    /**
     * 启用/停用/作废优惠券
     *
     * @param  CouponStatusRequest  $request  request
     * @param  int  $coupon  优惠券ID
     */
    public function updateStatus(CouponStatusRequest $request, int $coupon): JsonResponse
    {
        $result = $this->couponService->updateStatus($coupon, (int) $request->validated('status'));
        if ($result === null) {
            return $this->fail(trans('public.message.non_existent'));
        }

        return $this->succeed($result, trans('public.message.upd_success'));
    }

    #[QueryParameter(name: 'user_code', description: '用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'biz_id', description: '订单号', type: 'string', required: false)]
    #[QueryParameter(name: 'trade_account_type', description: '账号类型', type: 'integer', required: false)]
    #[QueryParameter(name: 'keyword', description: '优惠券名关键词', type: 'string', required: false)]
    #[QueryParameter(name: 'name_internal', description: '优惠券活动名（对内）', type: 'string', required: false)]
    #[QueryParameter(name: 'name_external', description: '优惠券活动名（对外）', type: 'string', required: false)]
    #[QueryParameter(name: 'coupon_type', description: '优惠券类型：1=抹亏券，2=交易返现券，3=手续费减免券，4=通用赠金券，5=限制型赠金券；0=赠金券（筛选通用+限制型）', type: 'integer', required: false)]
    #[QueryParameter(name: 'status', description: '状态：10=未领取，20=未激活，30=可使用，40=不可使用，50=已过期，60=已作废', type: 'integer', required: false)]
    #[QueryParameter(name: 'expired_status', description: '过期状态：0=未过期，1=已过期', type: 'integer', required: false)]
    #[QueryParameter(name: 'redeem_status', description: '核销状态：1=部分核销，2=已核销', type: 'integer', required: false)]
    #[QueryParameter(name: 'operator_name', description: '操作人', type: 'string', required: false)]
    #[QueryParameter(name: 'used_start_at', description: '核销开始时间，格式 Y-m-d H:i:s', type: 'string', required: false)]
    #[QueryParameter(name: 'used_end_at', description: '核销结束时间，格式 Y-m-d H:i:s', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[ScrambleResponse(
        description: '用户优惠券核销记录',
        type: 'array{data: array<int, array{id:int,biz_id:?string,trade_account_id:?int,trade_account_type:?int,trade_account_type_text:string,user_code:int,name_internal:string,name_external:array,name_external_text:string,coupon_type:int,coupon_type_text:string,triggered_at:?string,used_at:?string,start_at:?string,end_at:?string,position_size:?string,trade_volume:?string,total_amount:string,used_amount:string,status:int,status_text:string,expired_status:int,expired_status_text:string,redeem_status:int,redeem_status_text:string,operator_name:string}>, total_count:int}'
    )]
    /**
     * 优惠券核销用户记录
     */
    public function userCoupons(Request $request): JsonResponse
    {
        return $this->succeed($this->couponService->getUserCouponList(
            [
                'user_code' => $request->input('user_code'),
                'biz_id' => trim((string) $request->input('biz_id', '')),
                'trade_account_type' => $request->input('trade_account_type'),
                'keyword' => trim((string) $request->input('keyword', '')),
                'name_internal' => trim((string) $request->input('name_internal', '')),
                'name_external' => trim((string) $request->input('name_external', '')),
                'coupon_type' => $request->input('coupon_type'),
                'status' => $request->input('status'),
                'expired_status' => $request->input('expired_status'),
                'redeem_status' => $request->input('redeem_status'),
                'operator_name' => trim((string) $request->input('operator_name', '')),
                'used_start_at' => trim((string) $request->input('used_start_at', '')),
                'used_end_at' => trim((string) $request->input('used_end_at', '')),
            ],
            max(1, (int) $request->input('limit', 20)),
            max(0, (int) $request->input('offset', 0))
        ));
    }

    #[PathParameter(name: 'user_coupon', description: '用户优惠券实例ID', type: 'integer')]
    #[QueryParameter(name: 'ticket', description: '订单号', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[ScrambleResponse(
        description: '交易返现券/手续费减免券使用明细',
        type: 'array{data: array<int, array{id:int,ticket:string,position_size:?string,trade_volume:?string,open_time:?string,close_time:string}>, total_count:int}'
    )]
    /**
     * 交易返现券/手续费减免券使用明细
     */
    public function tradeCashbackDetails(Request $request, int $userCoupon): JsonResponse
    {
        $filters = [
            'ticket' => trim((string) $request->input('ticket', '')),
        ];
        $limit = max(1, (int) $request->input('limit', 20));
        $offset = max(0, (int) $request->input('offset', 0));

        $result = $this->couponService->getTradeCashbackDetailList($userCoupon, $filters, $limit, $offset);

        return $this->succeed($result);
    }

    #[PathParameter(name: 'user_coupon', description: '用户优惠券实例ID', type: 'integer')]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[ScrambleResponse(
        description: '信用赠金券详情（bonus_status：1=可用，0=不可用）',
        type: 'array{data: array<int, array{id:int,ticket:string,initial_bonus_amount:string,remaining_bonus_amount:string,unlocked_bonus_amount:string,currency:string,bonus_status:int,bonus_status_text:string,credited_at:string,expired_at:?string}>, total_count:int}'
    )]
    /**
     * 信用赠金券详情
     */
    public function creditBonusDetails(Request $request, int $userCoupon): JsonResponse
    {
        $limit = max(1, (int) $request->input('limit', 20));
        $offset = max(0, (int) $request->input('offset', 0));

        return $this->succeed(
            $this->couponService->getCreditBonusDetailList($userCoupon, $limit, $offset)
        );
    }
}
