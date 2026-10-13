<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\FundingLedger\FundingLedgerAddRequest;
use App\Http\Requests\FundingLedger\FundingLedgerListRequest;
use App\Http\Requests\FundingLedger\FundingLedgerManagementListRequest;
use App\Services\Api\FundingLedgerService;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

#[Group('Funds', '资金流水')]
class FundingLedgerController extends BaseController
{
    public function __construct(private FundingLedgerService $fundingLedgerService)
    {
    }

    #[Response(
        description: '资金类型列表',
        type: 'array<int, array{code:int,name:string}>'
    )]
    /**
     * 获取资金流水类型
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function types(): \Illuminate\Http\JsonResponse
    {
        return $this->succeed($this->fundingLedgerService->getTypeOptions());
    }

    #[Response(
        description: '资金管理类型列表',
        type: 'array<int, array{code:int,name:string}>'
    )]
    /**
     * 获取资金管理类型列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function managementTypes(): \Illuminate\Http\JsonResponse
    {
        return $this->succeed($this->fundingLedgerService->getManagementTypeOptions());
    }

    #[QueryParameter(name: 'user_code', description: '用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'created_start', description: '生成开始时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'created_end', description: '生成结束时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'fund_type', description: '资金类型 code', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: '资金流水列表',
        type: 'array{data: array<int, array{id:int,user_code:int,fund_type:int,fund_type_text:string,created_at:?string,from:string,to:string,amount:float,after_balance:float}>, total_count:int}'
    )]
    /**
     * 获取资金流水列表
     *
     * @param FundingLedgerListRequest $request request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(FundingLedgerListRequest $request): \Illuminate\Http\JsonResponse
    {
        $result = $this->getFundingLedgerList(
            $request,
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    public function export(FundingLedgerListRequest $request): \Symfony\Component\HttpFoundation\Response|\Illuminate\Http\JsonResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) $request->input('export_ext', 'xlsx'));

        $preview = $this->getFundingLedgerList($request, 1, 0);
        if ($response = $this->ensureExportWithinLimit((int) ($preview['total_count'] ?? 0))) {
            return $response;
        }

        $exportCount = (int) ($preview['total_count'] ?? 0);
        $result = $this->getFundingLedgerList($request, $exportCount, 0);

        $rows = array_map(static function (array $item): array {
            return [
                'user_code' => $item['user_code'] ?? '',
                'fund_type_text' => $item['fund_type_text'] ?? '',
                'created_at' => $item['created_at'] ?? '',
                'from' => $item['from'] ?? '',
                'to' => $item['to'] ?? '',
                'amount' => $item['amount'] ?? '',
                'after_balance' => $item['after_balance'] ?? '',
            ];
        }, $result['data'] ?? []);

        return ExportUtil::exportExcelDirect(
            sprintf('fund-flows-%s', now()->format('YmdHis')),
            trans('fund_flow.export.title'),
            $rows,
            $exportExtension
        );
    }

    #[QueryParameter(name: 'user_code', description: '用户ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'operation_source', description: '操作来源：1=系统，2=人工', type: 'integer', required: false)]
    #[QueryParameter(name: 'direction', description: '方向：1=收入，-1=支出', type: 'integer', required: false)]
    #[QueryParameter(name: 'created_start', description: '生成开始时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'created_end', description: '生成结束时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'fund_type', description: '资金类型 code；未传时使用默认管理类型范围', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: '资金管理列表',
        type: 'array{data: array<int, array{id:int,user_code:int,operation_source:string,direction:string,fund_type:int,fund_type_text:string,remark:string,reason:string,operator_name:string,payable_deduction_amount:int|float|string,amount:int|float|string,created_at:?string}>, total_count:int}'
    )]
    /**
     * 获取资金管理列表
     *
     * fund_type 未传时，由 service 层使用 FundingLedger::managementTypeOptions() 作为默认筛选范围。
     * payable_deduction_amount 表示应扣除金额，amount 表示本次实际流水金额。
     *
     * @param FundingLedgerManagementListRequest $request request
     *
     * @return JsonResponse
     */
    public function management(FundingLedgerManagementListRequest $request): JsonResponse
    {
        $filters = [
            'user_code' => $request->input('user_code'),
            'operation_source' => $request->input('operation_source'),
            'direction' => $request->input('direction'),
            'created_start' => $request->input('created_start'),
            'created_end' => $request->input('created_end'),
            'fund_type' => $request->input('fund_type'),
        ];

        $result = $this->fundingLedgerService->getManagementList(
            $filters,
            (int) $request->input('limit', 20),
            (int) $request->input('offset', 0)
        );

        return $this->succeed($result);
    }

    #[BodyParameter(name: 'user_codes', description: '用户ID，多个以 ; 分隔', type: 'string')]
    #[BodyParameter(name: 'fund_type', description: '资金类型 code', type: 'integer')]
    #[BodyParameter(name: 'direction', description: '管理方向：1=发放，-1=扣除', type: 'integer')]
    #[BodyParameter(name: 'amount', description: '操作金额', type: 'number')]
    #[BodyParameter(name: 'reason', description: '原因描述', type: 'string', required: false)]
    #[BodyParameter(name: 'activity_name', description: '活动名称', type: 'string', required: false)]
    #[Response(
        description: '新增资金流水结果',
        type: 'array{batch_id:string,total_count:int,success_count:int,failed_count:int,results: array<int, array{user_code:int,is_succ:bool,message:string,ledger_id?:int,amount?:string}>}'
    )]
    /**
     * 新增资金流水（人工发放/扣除）
     *
     * 支持多个用户批量处理
     * 返回批次号与逐用户处理结果
     * 单个用户失败不会中断整批结果返回
     * @param FundingLedgerAddRequest $request request
     *
     * @return JsonResponse
     */
    public function add(FundingLedgerAddRequest $request): JsonResponse
    {
        $filters = [
            'user_codes' => $request->userCodes(),
            'fund_type' => $request->input('fund_type'), //资金类型
            'direction' => $request->input('direction'), //管理方向:1发放，-1扣除
            'amount' => $request->input('amount'),
            'reason' => $request->input('reason'), //原因描述
            'activity_name' => $request->input('activity_name', ''), //活动名称
        ];

        $result = $this->fundingLedgerService->add($filters);
        $message = trans('public.message.add_success');
        if (($result['failed_count'] ?? 0) > 0) {
            $failedMessages = array_map(
                static fn (array $item): string => trans('funding_ledger.message.failed_item', [
                    'user_code' => (string) ($item['user_code'] ?? '-'),
                    'reason' => (string) ($item['message'] ?? ''),
                ]),
                array_values(array_filter(
                    $result['results'] ?? [],
                    static fn (array $item): bool => !((bool) ($item['is_succ'] ?? false))
                ))
            );

            $message = trans(
                ($result['total_count'] ?? 0) > 1
                    ? 'funding_ledger.message.batch_failed'
                    : 'funding_ledger.message.single_failed',
                [
                    'details' => implode((string) trans('funding_ledger.message.failed_separator'), $failedMessages),
                ]
            );
        }

        return $this->succeed($result, $message);
    }

    private function getFundingLedgerList(FundingLedgerListRequest $request, int $limit, int $offset): array
    {
        return $this->fundingLedgerService->getList(
            [
                'user_code' => $request->input('user_code'),
                'created_start' => $request->input('created_start'),
                'created_end' => $request->input('created_end'),
                'fund_type' => $request->input('fund_type'),
            ],
            $limit,
            $offset
        );
    }
}
