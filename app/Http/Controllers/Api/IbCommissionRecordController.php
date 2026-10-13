<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\IbCommissionRecord\IbCommissionAllocationListRequest;
use App\Http\Requests\IbCommissionRecord\IbCommissionRecordListRequest;
use App\Http\Requests\IbCommissionRecord\IbCommissionRecordRejectRequest;
use App\Services\Api\IbCommissionRecordService;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Group('IB Commission', 'IB 佣金记录（ib_commission_record / ib_commission_allocation）')]
class IbCommissionRecordController extends BaseController
{
    public function __construct(private IbCommissionRecordService $ibCommissionRecordService) {}

    #[QueryParameter(name: 'ticket', description: '订单ID', type: 'string', required: false)]
    #[QueryParameter(name: 'source', description: '交易品种', type: 'string', required: false)]
    #[QueryParameter(name: 'close_time_start', description: '订单平台开始时间，格式：YYYY-MM-DD 或 YYYY-MM-DD HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'close_time_end', description: '订单平台结束时间，格式：YYYY-MM-DD 或 YYYY-MM-DD HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'settled_at_start', description: '结算开始时间，格式：YYYY-MM-DD 或 YYYY-MM-DD HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'settled_at_end', description: '结算结束时间，格式：YYYY-MM-DD 或 YYYY-MM-DD HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'settlement_status', description: '结算状态：0=未结算，1=已结算，2=已拒绝', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[QueryParameter(name: 'export', description: '是否导出', type: 'boolean', required: false, default: false)]
    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    #[Response(
        description: "佣金记录列表（分页）\n- settlement_status: 结算状态（0=未结算，1=已结算，2=已拒绝）\n- can_reject_settlement: 是否可拒绝结算（1是/0否）",
        type: 'array{data: array<int, array{id:int,ticket:string,source:string,position_size:string,commission_amount:string,close_time:string,estimated_settlement_at:string,settled_at:?string,rejection_reason:?string,settlement_status:int,settlement_status_text:string,can_reject_settlement:int}>, total_count:int}',
        examples: [[
            'data' => [
                [
                    'id' => 1,
                    'ticket' => '243497832021',
                    'source' => 'AUDCAD',
                    'position_size' => '10.000000',
                    'commission_amount' => '50.000000',
                    'close_time' => '2026-03-26 23:55:48',
                    'estimated_settlement_at' => '2026-03-27 23:55:48',
                    'settled_at' => null,
                    'rejection_reason' => null,
                    'settlement_status' => 0,
                    'settlement_status_text' => '未结算',
                    'can_reject_settlement' => 1,
                ],
            ],
            'total_count' => 1,
        ]]
    )]
    /**
     * 获取佣金记录列表
     *
     * Query:
     * - ticket:string 订单ID
     * - source:string 交易品种
     * - close_time_start:string 订单平台开始时间
     * - close_time_end:string 订单平台结束时间
     * - settled_at_start:string 结算开始时间
     * - settled_at_end:string 结算结束时间
     * - settlement_status:int 结算状态（0未结算，1已结算，2已拒绝）
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     * - export:bool 是否导出
     * - export_ext:string 导出格式（csv/xls/xlsx）
     *
     * Response.data[].fields:
     * - id:int 佣金记录ID
     * - ticket:string 订单ID
     * - source:string 交易品种
     * - position_size:string 持仓量Position Size
     * - commission_amount:string 佣金金额
     * - close_time:string 订单平台时间
     * - estimated_settlement_at:string 预计结算时间
     * - settled_at:?string 结算时间
     * - rejection_reason:?string 拒绝原因
     * - settlement_status:int 结算状态（0未结算，1已结算，2已拒绝）
     * - settlement_status_text:string 结算状态文案
     * - can_reject_settlement:int 是否可拒绝结算（1是/0否）
     *
     * @param  IbCommissionRecordListRequest  $request  request
     */
    public function index(IbCommissionRecordListRequest $request): JsonResponse|BinaryFileResponse
    {
        $limit = (int) $request->input('limit', 20);
        $offset = (int) $request->input('offset', 0);
        $export = $request->boolean('export', false);
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        if ($export) {
            $limit = $offset = -1;
        }

        $result = $this->ibCommissionRecordService->getList(
            [
                'ticket' => $request->input('ticket'),
                'source' => $request->input('source'),
                'close_time_start' => $request->input('close_time_start'),
                'close_time_end' => $request->input('close_time_end'),
                'settled_at_start' => $request->input('settled_at_start'),
                'settled_at_end' => $request->input('settled_at_end'),
                'settlement_status' => $request->input('settlement_status'),
            ],
            $limit,
            $offset
        );

        if ($export) {
            $filename = sprintf('ib-commission-records-%s', now()->format('YmdHis'));
            $exportData = [];
            foreach ($result['data'] as $item) {
                $exportData[] = [
                    'ticket' => $item['ticket'] ?? '',
                    'source' => $item['source'] ?? '',
                    'position_size' => $item['position_size'] ?? '',
                    'commission_amount' => $item['commission_amount'] ?? '',
                    'close_time' => $item['close_time'] ?? '',
                    'estimated_settlement_at' => $item['estimated_settlement_at'] ?? '',
                    'settled_at' => $item['settled_at'] ?? '',
                    'rejection_reason' => $item['rejection_reason'] ?? '',
                    'settlement_status_text' => $item['settlement_status_text'] ?? '',
                ];
            }

            return ExportUtil::exportExcelDirect(
                $filename,
                trans('ib_commission_record.export.title'),
                $exportData,
                $exportExtension
            );
        }

        return $this->succeed($result);
    }

    #[BodyParameter('commission_record_id', '佣金记录ID，必填', required: true, type: 'integer')]
    #[BodyParameter('rejection_reason', '拒绝原因，必填', required: true, type: 'string')]
    /**
     * 拒绝结算
     *
     * Body:
     * - commission_record_id:int 佣金记录ID，必填
     * - rejection_reason:string 拒绝原因，必填
     *
     * @param  IbCommissionRecordRejectRequest  $request  request
     */
    public function reject(IbCommissionRecordRejectRequest $request): JsonResponse
    {
        $this->ibCommissionRecordService->reject(
            (int) $request->input('commission_record_id'),
            (string) $request->input('rejection_reason')
        );

        return $this->succeed();
    }

    #[PathParameter(name: 'commission_record_id', description: '佣金记录ID（路由参数，必填）', type: 'integer', required: true)]
    #[QueryParameter(name: 'user_code', description: '代理用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'level_id', description: '代理等级ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: "佣金分配详情（分页）\n- 展示某个订单的佣金分配明细",
        type: 'array{data: array<int, array{user_code:int,level_id:int,level_name:string,commission_amount:string}>, total_count:int}',
        examples: [[
            'data' => [
                [
                    'user_code' => 1,
                    'level_id' => 3,
                    'level_name' => '黄金',
                    'commission_amount' => '10.000000',
                ],
            ],
            'total_count' => 1,
        ]]
    )]
    /**
     * 获取某个订单的佣金分配详情
     *
     * Path:
     * - commission_record_id:int 佣金记录ID（路由参数，必填）
     *
     * Query:
     * - user_code:int 代理用户ID
     * - level_id:int 代理等级ID
     * - limit:int 每页条数
     * - offset:int 分页偏移量
     *
     * Response.data[].fields:
     * - user_code:int 用户ID
     * - level_id:int 代理等级ID
     * - level_name:string 代理等级
     * - commission_amount:string 分配佣金金额
     *
     * @param  IbCommissionAllocationListRequest  $request  request
     * @param  int  $commission_record_id  佣金记录ID（路由参数）
     */
    public function allocations(IbCommissionAllocationListRequest $request, int $commission_record_id): JsonResponse
    {
        $result = $this->ibCommissionRecordService->getAllocations(
            $commission_record_id,
            [
                'user_code' => $request->input('user_code'),
                'level_id' => $request->input('level_id'),
            ],
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[QueryParameter(name: 'config', description: '是否返回新增佣金规则配置字段，true 时追加 commission_fee_rate / is_support_open_close_fee / direction_details', type: 'boolean', required: false, default: false)]
    #[Response(
        description: "交易品种列表\n- 默认只返回 key/value\n- config=true 时追加新增佣金规则所需配置字段",
        type: 'array<int, array<string, mixed>>',
        examples: [[
            ['key' => 'AUDCAD', 'value' => 'AUDCAD'],
            [
                'key' => 'BTCUSD',
                'value' => 'BTCUSD',
                'source' => 'BTCUSD',
                'source_name' => '比特币/美元',
                'is_source_deleted' => false,
                'settlement_unit' => '1000',
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
        ]]
    )]
    /**
     * 获取交易品种列表
     *
     * Query:
     * - config:bool 是否返回新增佣金规则配置字段
     *
     * Response.fields:
     * - key:string 交易品种值
     * - value:string 交易品种名称
     * - source:string config=true 时返回，品种代码
     * - source_name:string config=true 时返回，当前语言下的品种名称
     * - is_source_deleted:bool config=true 时返回，品种是否已删除；sources 只读 ts_source，固定为 false
     * - settlement_unit:string config=true 时返回，新增规则默认 1000
     * - commission_amount:string config=true 时返回，新增规则默认空字符串
     * - commission_fee_rate:string config=true 时返回，新增规则默认空字符串
     * - is_support_open_close_fee:bool config=true 时返回，是否支持开平仓费用
     * - direction_details:array config=true 时返回，开平仓费率与费用明细
     */
    public function sources(): JsonResponse
    {
        $config = request()->input('config', false);
        $withConfig = request()->boolean('config') || strtolower((string) $config) === 'ture';

        return $this->succeed($this->ibCommissionRecordService->getSourceOptions($withConfig));
    }

    #[Response(
        description: "佣金统计\n- settled_commission_amount: 累计已结算佣金\n- pending_commission_amount: 待结算佣金",
        type: 'array{settled_commission_amount:string,pending_commission_amount:string}',
        examples: [[
            'settled_commission_amount' => '734.1234',
            'pending_commission_amount' => '734.1234',
        ]]
    )]
    /**
     * 获取佣金统计
     *
     * Path:
     * - user_code:int IB 代理用户ID（路由参数，必填）
     *
     * Response.fields:
     * - settled_commission_amount:string 累计已结算佣金
     * - pending_commission_amount:string 待结算佣金
     *
     * @param  int  $user_code  IB 代理用户ID（路由参数）
     */
    public function statistics(int $user_code): JsonResponse
    {
        $result = $this->ibCommissionRecordService->getStatistics([
            'user_code' => $user_code,
        ]);

        return $this->succeed($result);
    }
}
