<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Requests\WalletImport\WalletImportDetailListRequest;
use App\Models\Api\FundingOperationRecord;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Group('Funds', '批量资金任务明细')]
class WalletImportDetailController extends BaseController
{
    #[QueryParameter(name: 'status', description: '明细状态：0=待执行,1=执行中,2=成功,3=执行失败,4=导入无效', type: 'integer', required: false)]
    #[QueryParameter(name: 'user_code', description: '用户 ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: '批次任务明细列表。按 funding_operation_record 返回，并补充状态文案。',
        type: 'array{data: array<int, array{id:int,job_id:int,user_code:int,amount:string,activity_name:string,reason:string,status:int,status_text:string,error_message:string,created_at:string,updated_at:string}>, total_count:int, summary: array{job_id:int,total_count:int,pending_count:int,processing_count:int,success_count:int,fail_count:int,invalid_count:int,total_amount:string,success_amount:string,fail_amount:string}}',
        examples: [[
            'data' => [
                [
                    'id' => 1001,
                    'job_id' => 101,
                    'user_code' => 10000001,
                    'amount' => '100.000000',
                    'activity_name' => '五月回馈活动',
                    'reason' => '活动补发',
                    'status' => 0,
                    'status_text' => '待执行',
                    'error_message' => '',
                    'created_at' => '2026-05-15 10:00:00',
                    'updated_at' => '2026-05-15 10:00:00',
                ],
            ],
            'total_count' => 5,
            'summary' => [
                'job_id' => 101,
                'total_count' => 5,
                'pending_count' => 2,
                'processing_count' => 0,
                'success_count' => 0,
                'fail_count' => 0,
                'invalid_count' => 1,
                'total_amount' => '350.500000',
                'success_amount' => '0.000000',
                'fail_amount' => '0.000000',
            ],
        ]]
    )]
    /**
     * 批量操作-列表-详情
     *
     * Path:
     * - job_id:int 必填，批次任务 ID
     *
     * Query:
     * - status:int 明细状态筛选
     * - user_code:int 用户 ID 筛选
     * - limit:int 每页条数，默认 20
     * - offset:int 分页偏移量，默认 0
     *
     * Response.fields:
     * - data:array 明细列表
     * - data[].id:int 明细 ID，对应 `funding_operation_record.id`
     * - data[].job_id:int 批次任务 ID
     * - data[].user_code:int 用户 ID
     * - data[].amount:string 金额
     * - data[].activity_name:string 活动名称
     * - data[].reason:string 内部原因描述
     * - data[].status:int 明细状态：0=待执行,1=执行中,2=成功,3=执行失败,4=导入无效
     * - data[].status_text:string 明细状态文案
     * - data[].error_message:string 失败原因；成功或待执行时为空字符串
     * - data[].created_at:string 创建时间
     * - data[].updated_at:string 更新时间
     * - total_count:int 当前筛选条件下的总记录数
     * - summary:object 当前批次汇总信息，前端可用于顶部统计展示
     * - summary.job_id:int 批次任务 ID
     * - summary.total_count:int 总条数
     * - summary.pending_count:int 待执行条数
     * - summary.processing_count:int 执行中条数
     * - summary.success_count:int 成功条数
     * - summary.fail_count:int 执行失败条数
     * - summary.invalid_count:int 导入无效条数
     * - summary.total_amount:string 导入总金额（按明细实时聚合）
     * - summary.success_amount:string 成功总金额（status=2）
     * - summary.fail_amount:string 失败总金额（status in 3,4）
     *
     * @return JsonResponse
     */
    public function getList(WalletImportDetailListRequest $request, int $job_id): JsonResponse
    {
        if (! Schema::connection(DB_BM)->hasTable(FundingOperationRecord::TABLE)) {
            return $this->succeed([
                'data' => [],
                'total_count' => 0,
                'summary' => [
                    'job_id' => $job_id,
                    'total_count' => 0,
                    'pending_count' => 0,
                    'processing_count' => 0,
                    'success_count' => 0,
                    'fail_count' => 0,
                    'invalid_count' => 0,
                    'total_amount' => '0.000000',
                    'success_amount' => '0.000000',
                    'fail_amount' => '0.000000',
                ],
            ]);
        }

        $baseQuery = DB::connection(DB_BM)
            ->table(FundingOperationRecord::TABLE . ' as fbjd')
            ->where('fbjd.job_id', $job_id);

        $query = clone $baseQuery;

        if ($request->filled('status')) {
            $query->where('fbjd.status', (int) $request->input('status'));
        }

        if ($request->filled('user_code')) {
            $query->where('fbjd.user_code', (int) $request->input('user_code'));
        }

        $totalCount = (clone $query)->count('fbjd.id');

        $rows = $query
            ->orderByDesc('fbjd.id')
            ->offset((int) $request->input('offset', 0))
            ->limit((int) $request->input('limit', 20))
            ->get()
            ->map(static fn ($item) => (array) $item)
            ->all();

        $summaryRows = (clone $baseQuery)
            ->select(
                'fbjd.status',
                DB::raw('COUNT(*) as aggregate_count'),
                DB::raw('COALESCE(SUM(fbjd.amount), 0) as aggregate_amount')
            )
            ->groupBy('fbjd.status')
            ->get()
            ->map(static fn ($item) => (array) $item)
            ->all();

        $summaryMap = [];
        foreach ($summaryRows as $summaryRow) {
            $summaryMap[(int) ($summaryRow['status'] ?? -1)] = [
                'count' => (int) ($summaryRow['aggregate_count'] ?? 0),
                'amount' => $this->decimalString($summaryRow['aggregate_amount'] ?? '0'),
            ];
        }

        $totalAmount = '0.000000';
        $successAmount = '0.000000';
        $failAmount = '0.000000';
        foreach ($summaryMap as $status => $summaryItem) {
            $amount = (string) ($summaryItem['amount'] ?? '0.000000');
            $totalAmount = $this->addDecimal($totalAmount, $amount);
            if ($status === FundingOperationRecord::STATUS_SUCCESS) {
                $successAmount = $this->addDecimal($successAmount, $amount);
            }
            if (in_array($status, [FundingOperationRecord::STATUS_FAIL, FundingOperationRecord::STATUS_INVALID], true)) {
                $failAmount = $this->addDecimal($failAmount, $amount);
            }
        }

        $data = array_map(fn (array $row): array => $this->mapDetailRow($row, $job_id), $rows);

        return $this->succeed([
            'data' => $data,
            'total_count' => $totalCount,
            'summary' => [
                'job_id' => $job_id,
                'total_count' => array_sum(array_map(static fn (array $item): int => (int) ($item['count'] ?? 0), $summaryMap)),
                'pending_count' => (int) ($summaryMap[FundingOperationRecord::STATUS_PENDING]['count'] ?? 0),
                'processing_count' => (int) ($summaryMap[FundingOperationRecord::STATUS_PROCESSING]['count'] ?? 0),
                'success_count' => (int) ($summaryMap[FundingOperationRecord::STATUS_SUCCESS]['count'] ?? 0),
                'fail_count' => (int) ($summaryMap[FundingOperationRecord::STATUS_FAIL]['count'] ?? 0),
                'invalid_count' => (int) ($summaryMap[FundingOperationRecord::STATUS_INVALID]['count'] ?? 0),
                'total_amount' => $totalAmount,
                'success_amount' => $successAmount,
                'fail_amount' => $failAmount,
            ],
        ]);
    }

    #[QueryParameter(name: 'status', description: '明细状态：0=待执行,1=执行中,2=成功,3=执行失败,4=导入无效', type: 'integer', required: false)]
    #[QueryParameter(name: 'user_code', description: '用户 ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    #[Response(
        description: '批次任务明细导出。直接返回导出文件流，支持 csv/xls/xlsx。',
        type: 'binary',
        examples: [[
            'file_name' => 'funding_operation_101_details_20260515.xlsx',
            'export_ext' => 'xlsx',
        ]]
    )]
    /**
     * 批量操作-列表-详情导出
     *
     * Path:
     * - job_id:int 必填，批次任务 ID
     *
     * Query:
     * - status:int 明细状态筛选
     * - user_code:int 用户 ID 筛选
     * - export_ext:string 导出格式，默认 xlsx
     *
     * @param Request $request request
     *
     * @return JsonResponse|BinaryFileResponse
     */
    public function export(Request $request, int $job_id): JsonResponse|BinaryFileResponse
    {
        $exportExt = ExportUtil::normalizeExtension((string) $request->query('export_ext', 'xlsx'));
        $query = DB::connection(DB_BM)
            ->table(FundingOperationRecord::TABLE . ' as fbjd')
            ->where('fbjd.job_id', $job_id);

        if ($request->query('status') !== null) {
            $query->where('fbjd.status', (int) $request->query('status'));
        }

        if ($request->query('user_code') !== null) {
            $query->where('fbjd.user_code', (int) $request->query('user_code'));
        }

        $rows = [];
        if (Schema::connection(DB_BM)->hasTable(FundingOperationRecord::TABLE)) {
            $rows = $query
                ->orderByDesc('fbjd.id')
                ->get()
                ->map(function ($item) use ($job_id): array {
                    return $this->mapDetailRow((array) $item, $job_id);
                })
                ->all();
        }

        $exportRows = array_map(static function (array $row): array {
            return [
                'id' => $row['id'],
                'job_id' => $row['job_id'],
                'user_code' => $row['user_code'],
                'amount' => $row['amount'],
                'activity_name' => $row['activity_name'],
                'reason' => $row['reason'],
                'status' => $row['status_text'],
                'error_message' => $row['error_message'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }, $rows);

        return ExportUtil::exportExcelDirect(
            sprintf('funding-operation-%d-details-%s', $job_id, now()->format('YmdHis')),
            ['ID', '批次ID', '用户ID', '金额', '活动名称', '内部原因描述', '状态', '失败原因', '创建时间', '更新时间'],
            $exportRows,
            $exportExt
        );
    }

    /**
     * @param mixed $value
     */
    private function decimalString($value): string
    {
        if ($value === null || $value === '') {
            return '0.000000';
        }

        return number_format((float) $value, 6, '.', '');
    }

    private function addDecimal(string $left, string $right): string
    {
        if (function_exists('bcadd')) {
            return bcadd($left, $right, 6);
        }

        return $this->decimalString((string) ((float) $left + (float) $right));
    }

    /**
     * @param mixed $value
     */
    private function nullableDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, int|string>
     */
    private function mapDetailRow(array $row, int $job_id): array
    {
        $status = (int) ($row['status'] ?? FundingOperationRecord::STATUS_PENDING);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'job_id' => (int) ($row['job_id'] ?? $job_id),
            'user_code' => (int) ($row['user_code'] ?? 0),
            'amount' => $this->decimalString($row['amount'] ?? '0'),
            'activity_name' => (string) ($row['activity_name'] ?? ''),
            'reason' => (string) ($row['reason'] ?? ''),
            'status' => $status,
            'status_text' => FundingOperationRecord::statusTextMap()[$status] ?? '',
            'error_message' => (string) ($row['error_message'] ?? ''),
            'created_at' => $this->nullableDateTime($row['created_at'] ?? null) ?? '',
            'updated_at' => $this->nullableDateTime($row['updated_at'] ?? null) ?? '',
        ];
    }
}
