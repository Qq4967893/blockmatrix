<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Jobs\WalletImportExecuteJob;
use App\Http\Requests\WalletImport\WalletImportExecuteRequest;
use App\Http\Requests\WalletImport\WalletImportListRequest;
use App\Imports\ArrayImport;
use App\Models\Api\FundingOperation;
use App\Models\Api\FundingOperationRecord;
use App\Models\Api\FundingLedger;
use App\Models\Api\Money;
use App\Models\Blockmatrix\AdminUser;
use App\Services\Api\WalletImportExecuteService;
use App\Utils\ExportUtil;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Scaffold\Framework\Enums\TypeLedger;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Group('Funds', '批量资金任务')]
class WalletImportController extends BaseController
{
    private const HEADER_USER_CODE = '用户ID';
    private const HEADER_AMOUNT = '金额($)';
    private const HEADER_REASON = '原因描述';
    private const HEADER_ACTIVITY_NAME = '活动名称';
    private const FUND_TYPE_BONUS = 13;
    private const MAX_IMPORT_ROWS = 500;

    #[QueryParameter(name: 'fund_type', description: '资金类型', type: 'integer', required: false)]
    #[QueryParameter(name: 'direction', description: '资金方向：1=发放，-1=扣除', type: 'integer', required: false)]
    #[QueryParameter(name: 'export_ext', description: '导出格式（csv/xls/xlsx）', type: 'string', required: false, default: 'xlsx')]
    #[Response(
        description: '批量导入模板下载。赠金模板包含活动名称列，其他资金类型模板不包含活动名称列。',
        type: 'binary',
        examples: [[
            'file_name' => 'funding_batch_import_template.xlsx',
            'export_ext' => 'xlsx',
        ]]
    )]
    /**
     * 批量操作-模板下载
     *
     * Query:
     * - fund_type:int 资金类型，可选。赠金模板包含活动名称列，其他类型不包含活动名称列。
     * - direction:int 资金方向，可选。当前版本仅用于前端透传。
     *
     * @return JsonResponse|BinaryFileResponse
     */
    public function getTemplate(): JsonResponse|BinaryFileResponse
    {
        $exportExtension = ExportUtil::normalizeExtension((string) request()->input('export_ext', 'xlsx'));
        $fundType = (int) request()->input('fund_type');
        $isBonus = $this->isBonusFundType($fundType);

        return ExportUtil::exportExcelDirect(
            sprintf('funding-batch-import-template-%s', now()->format('YmdHis')),
            $this->importTemplateHeaders($isBonus),
            [$this->importTemplateExampleRow($isBonus)],
            $exportExtension
        );
    }

    #[BodyParameter(name: 'file', description: '上传的 Excel 文件，form-data 文件字段名固定为 file', type: 'file', required: true)]
    #[BodyParameter(name: 'direction', description: '资金方向：1=发放，-1=扣除', type: 'integer', required: true)]
    #[BodyParameter(name: 'fund_type', description: '资金类型', type: 'integer', required: true)]
    #[BodyParameter(name: 'currency', description: '金额币种，默认 USDT', type: 'string', required: false)]
    #[BodyParameter(name: 'remark', description: '批次备注', type: 'string', required: false)]
    #[Response(
        description: '批量上传结果。成功时返回批次主表基础信息和导入统计，供前端跳转详情页或刷新列表。',
        type: 'array{id:int,direction:int,fund_type:int,fund_type_text:string,currency:string,file_name:string,total_count:int,valid_count:int,success_count:int,fail_count:int,total_amount:string,success_amount:string,status:int,status_text:string,remark:string,created_by:int,created_by_name:string,created_at:string}',
        examples: [[
            'id' => 101,
            'direction' => 1,
            'fund_type' => 3,
            'fund_type_text' => '补偿金',
            'currency' => Money::DEFAULT_CURRENCY,
            'file_name' => 'funding_batch_20260515.xlsx',
            'total_count' => 5,
            'valid_count' => 4,
            'success_count' => 0,
            'fail_count' => 1,
            'total_amount' => '350.500000',
            'success_amount' => '0.000000',
            'status' => 0,
            'status_text' => '待执行',
            'remark' => '5月批量补发',
            'created_by' => 10001,
            'created_by_name' => 'Mock Admin',
            'created_at' => '2026-05-15 10:00:00',
        ]]
    )]
    /**
     * 批量操作-上传
     *
     * 上传并解析 Excel，写入 funding_operation / funding_operation_record。
     * 赠金模板：用户ID、金额($)、原因描述、活动名称；其他类型模板：用户ID、金额($)、原因描述。
     *
     * Body:
     * - file:file 必填，Excel 文件
     * - direction:int 必填，1=发放，-1=扣除
     * - fund_type:int 必填，资金类型
     * - currency:string 可选，默认 USDT
     * - remark:string 可选，批次备注
     *
     * Response.fields:
     * - id:int 批次任务 ID，对应 `funding_operation.id`
     * - direction:int 资金方向
     * - fund_type:int 资金类型
     * - fund_type_text:string 资金类型文案，前端直接展示使用
     * - currency:string 金额币种
     * - file_name:string 原始上传文件名
     * - total_count:int 导入总条数
     * - valid_count:int 有效条数；导入后可进入执行流程的条数
     * - success_count:int 当前成功条数；上传后通常为 0
     * - fail_count:int 当前失败条数；上传阶段表示导入无效条数
     * - total_amount:string 导入总金额；通常按有效数据求和
     * - success_amount:string 当前成功金额；上传后通常为 0
     * - status:int 批次状态，0=待执行
     * - status_text:string 批次状态文案
     * - remark:string 批次备注
     * - created_by:int 提交管理员 ID
     * - created_by_name:string 提交管理员姓名，前端列表展示使用
     * - created_at:string 创建时间
     *
     * @param Request $request request
     *
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        if (! Schema::connection(DB_BM)->hasTable(FundingOperation::TABLE)
            || ! Schema::connection(DB_BM)->hasTable(FundingOperationRecord::TABLE)) {
            return $this->fail(trans('public.message.non_existent'));
        }

        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return $this->fail(trans('public.message.data_error'));
        }

        $direction = (int) $request->input('direction');
        if (! in_array($direction, FundingLedger::directionOptions(), true)) {
            return $this->fail(trans('public.message.data_error'));
        }

        $fundType = (int) $request->input('fund_type');
        if ($fundType <= 0) {
            return $this->fail(trans('public.message.data_error'));
        }

        try {
            $sheets = Excel::toArray(new ArrayImport(), $file);
        } catch (\Throwable) {
            return $this->fail('文件格式错误，请上传正确的 Excel 模板');
        }

        $isBonus = $this->isBonusFundType($fundType);
        if (! $this->isValidImportTemplate($sheets, $isBonus)) {
            return $this->fail('模板格式错误，正确格式为：' . $this->importTemplateFormatText($isBonus));
        }

        $rows = $this->extractImportRows($sheets);
        if ($rows === []) {
            return $this->fail(trans('public.message.data_error'));
        }

        if (count($rows) > self::MAX_IMPORT_ROWS) {
            return $this->fail(trans('user.limit_exceeded'));
        }

        $currency = (string) $request->input('currency', Money::DEFAULT_CURRENCY);
        $remark = (string) $request->input('remark', '');
        $loginUser = Auth::user();
        $createdBy = (int) ($loginUser['id'] ?? 0);
        $createdByName = (string) ($loginUser['login_name'] ?? '');
        $normalizedDetails = [];
        $validCount = 0;
        $failCount = 0;
        $totalMicros = 0;

        foreach ($rows as $row) {
            $normalized = $this->normalizeImportRow($row, $isBonus);
            $normalizedDetails[] = $normalized;
            if ($normalized['status'] === FundingOperationRecord::STATUS_PENDING) {
                $validCount++;
                $totalMicros += $normalized['amount_micros'];
            } else {
                $failCount++;
            }
        }

        $totalCount = count($normalizedDetails);
        $fileName = $file->getClientOriginalName() ?: sprintf('funding_batch_%s.xlsx', now()->format('YmdHis'));

        $createdAt = now()->format(Money::DATETIME_FORMAT);
        $jobColumns = Schema::connection(DB_BM)->getColumnListing(FundingOperation::TABLE);
        $detailColumns = Schema::connection(DB_BM)->getColumnListing(FundingOperationRecord::TABLE);

        $jobId = DB::connection(DB_BM)->transaction(function () use (
            $jobColumns,
            $detailColumns,
            $direction,
            $fundType,
            $currency,
            $remark,
            $fileName,
            $createdBy,
            $totalCount,
            $validCount,
            $failCount,
            $totalMicros,
            $normalizedDetails
        ): int {
            $jobPayload = $this->filterPayloadByColumns([
                'direction' => $direction,
                'fund_type' => $fundType,
                'currency' => $currency,
                'file_name' => $fileName,
                'total_count' => $totalCount,
                'valid_count' => $validCount,
                'success_count' => 0,
                'fail_count' => $failCount,
                'total_amount' => $this->formatMicros($totalMicros),
                'success_amount' => '0.000000',
                'status' => FundingOperation::STATUS_PENDING,
                'remark' => $remark,
                'created_by' => $createdBy,
            ], $jobColumns);

            $jobId = (int) DB::connection(DB_BM)->table(FundingOperation::TABLE)->insertGetId($jobPayload);

            $detailPayloads = array_map(function (array $detail) use ($jobId, $detailColumns): array {
                return $this->filterPayloadByColumns([
                    'job_id' => $jobId,
                    'user_code' => $detail['user_code'],
                    'amount' => $detail['amount'],
                    'reason' => $detail['reason'],
                    'activity_name' => $detail['activity_name'],
                    'status' => $detail['status'],
                    'error_message' => $detail['error_message'],
                ], $detailColumns);
            }, $normalizedDetails);

            if ($detailPayloads !== []) {
                DB::connection(DB_BM)->table(FundingOperationRecord::TABLE)->insert($detailPayloads);
            }

            return $jobId;
        });

        return $this->succeed([
            'id' => $jobId,
            'direction' => $direction,
            'fund_type' => $fundType,
            'fund_type_text' => $this->ledgerTypeText($fundType),
            'currency' => $currency,
            'file_name' => $fileName,
            'total_count' => $totalCount,
            'valid_count' => $validCount,
            'success_count' => 0,
            'fail_count' => $failCount,
            'total_amount' => $this->formatMicros($totalMicros),
            'success_amount' => '0.000000',
            'status' => FundingOperation::STATUS_PENDING,
            'status_text' => '待执行',
            'remark' => $remark,
            'created_by' => $createdBy,
            'created_by_name' => $createdByName,
            'created_at' => $createdAt,
        ]);
    }

    #[QueryParameter(name: 'direction', description: '资金方向：1=发放，-1=扣除', type: 'integer', required: false)]
    #[QueryParameter(name: 'fund_type', description: '资金类型', type: 'integer', required: false)]
    #[QueryParameter(name: 'status', description: '批次状态：1=执行中,2=部分成功,3=成功,4=失败,5=已取消；列表不展示待执行批次', type: 'integer', required: false)]
    #[QueryParameter(name: 'created_by', description: '提交管理员 ID', type: 'integer', required: false)]
    #[QueryParameter(name: 'created_start', description: '创建开始时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'created_end', description: '创建结束时间 yyyy-mm-dd 或 yyyy-mm-dd HH:MM:SS', type: 'string', required: false)]
    #[QueryParameter(name: 'limit', description: '每页条数', type: 'integer', required: false, default: 20)]
    #[QueryParameter(name: 'offset', description: '分页偏移量', type: 'integer', required: false, default: 0)]
    #[Response(
        description: '批量任务列表。按 funding_operation 主表返回分页数据，并补充状态文案、方向文案。',
        type: 'array{data: array<int, array{id:int,direction:int,direction_text:string,fund_type:int,fund_type_text:string,currency:string,file_name:string,total_count:int,valid_count:int,success_count:int,fail_count:int,total_amount:string,success_amount:string,status:int,status_text:string,remark:string,created_by:int,created_by_name:string,executed_at:?string,finished_at:?string,created_at:string,updated_at:string}>, total_count:int}',
        examples: [[
            'data' => [
                [
                    'id' => 101,
                    'direction' => 1,
                    'direction_text' => '发放',
                    'fund_type' => 3,
                    'fund_type_text' => '补偿金',
                    'currency' => Money::DEFAULT_CURRENCY,
                    'file_name' => 'funding_batch_20260515.xlsx',
                    'total_count' => 5,
                    'valid_count' => 4,
                    'success_count' => 4,
                    'fail_count' => 1,
                    'total_amount' => '350.500000',
                    'success_amount' => '300.500000',
                    'status' => 2,
                    'status_text' => '部分成功',
                    'remark' => '5月批量补发',
                    'created_by' => 10001,
                    'created_by_name' => 'Mock Admin',
                    'executed_at' => null,
                    'finished_at' => null,
                    'created_at' => '2026-05-15 10:00:00',
                    'updated_at' => '2026-05-15 10:00:00',
                ],
            ],
            'total_count' => 1,
        ]]
    )]
    /**
     * 批量操作-列表
     *
     * Query:
     * - direction:int 资金方向
     * - fund_type:int 资金类型
     * - status:int 批次状态：1=执行中,2=部分成功,3=成功,4=失败,5=已取消；列表不展示待执行批次
     * - created_by:int 提交管理员 ID
     * - created_start:string 创建开始时间
     * - created_end:string 创建结束时间
     * - limit:int 每页条数，默认 20
     * - offset:int 分页偏移量，默认 0
     *
     * Response.fields:
     * - data:array 批次列表
     * - data[].id:int 批次 ID
     * - data[].direction:int 资金方向
     * - data[].direction_text:string 资金方向文案
     * - data[].fund_type:int 资金类型
     * - data[].fund_type_text:string 资金类型文案
     * - data[].currency:string 币种
     * - data[].file_name:string 上传文件名
     * - data[].total_count:int 总条数
     * - data[].valid_count:int 有效条数
     * - data[].success_count:int 成功条数
     * - data[].fail_count:int 失败条数
     * - data[].total_amount:string 导入总金额
     * - data[].success_amount:string 成功总金额
     * - data[].status:int 批次状态：1=执行中,2=部分成功,3=成功,4=失败,5=已取消
     * - data[].status_text:string 批次状态文案
     * - data[].remark:string 备注
     * - data[].created_by:int 提交管理员 ID
     * - data[].created_by_name:string 提交管理员姓名，取后台管理员展示名
     * - data[].executed_at:?string 开始执行时间
     * - data[].finished_at:?string 完成时间
     * - data[].created_at:string 创建时间
     * - data[].updated_at:string 更新时间
     * - total_count:int 总记录数
     *
     * @return JsonResponse
     */
    public function getList(WalletImportListRequest $request): JsonResponse
    {
        if (! Schema::connection(DB_BM)->hasTable(FundingOperation::TABLE)) {
            return $this->succeed([
                'data' => [],
                'total_count' => 0,
            ]);
        }

        $query = DB::connection(DB_BM)
            ->table(FundingOperation::TABLE . ' as fbj')
            ->whereIn('fbj.status', $this->walletImportListStatuses());

        if ($request->filled('direction')) {
            $query->where('fbj.direction', (int) $request->input('direction'));
        }

        if ($request->filled('fund_type')) {
            $query->where('fbj.fund_type', (int) $request->input('fund_type'));
        }

        if ($request->filled('status')) {
            $query->where('fbj.status', (int) $request->input('status'));
        }

        if ($request->filled('created_by')) {
            $query->where('fbj.created_by', (int) $request->input('created_by'));
        }

        if ($request->filled('created_start')) {
            $query->where('fbj.created_at', '>=', $this->normalizeDateBoundary((string) $request->input('created_start'), true));
        }

        if ($request->filled('created_end')) {
            $query->where('fbj.created_at', '<=', $this->normalizeDateBoundary((string) $request->input('created_end'), false));
        }

        $totalCount = (clone $query)->count('fbj.id');

        $rows = $query
            ->orderByDesc('fbj.id')
            ->offset((int) $request->input('offset', 0))
            ->limit((int) $request->input('limit', 20))
            ->get()
            ->map(static fn ($item) => (array) $item)
            ->all();

        $adminNameMap = AdminUser::query()
            ->whereIn('id', array_values(array_filter(array_unique(array_map(
                static fn (array $row): int => (int) ($row['created_by'] ?? 0),
                $rows
            )))))
            ->pluck('login_name', 'id')
            ->toArray();

        $data = array_map(function (array $row) use ($adminNameMap): array {
            $id = (int) ($row['id'] ?? 0);
            $direction = (int) ($row['direction'] ?? 0);
            $fundType = (int) ($row['fund_type'] ?? 0);
            $status = (int) ($row['status'] ?? FundingOperation::STATUS_PENDING);
            $createdBy = (int) ($row['created_by'] ?? 0);

            return [
                'id' => $id,
                'direction' => $direction,
                'direction_text' => $this->directionText($direction),
                'fund_type' => $fundType,
                'fund_type_text' => $this->ledgerTypeText($fundType),
                'currency' => (string) ($row['currency'] ?? Money::DEFAULT_CURRENCY),
                'file_name' => (string) ($row['file_name'] ?? ''),
                'total_count' => (int) ($row['total_count'] ?? 0),
                'valid_count' => (int) ($row['valid_count'] ?? 0),
                'success_count' => (int) ($row['success_count'] ?? 0),
                'fail_count' => (int) ($row['fail_count'] ?? 0),
                'total_amount' => $this->decimalString($row['total_amount'] ?? '0'),
                'success_amount' => $this->decimalString($row['success_amount'] ?? '0'),
                'status' => $status,
                'status_text' => FundingOperation::statusTextMap()[$status] ?? '',
                'remark' => (string) ($row['remark'] ?? ''),
                'created_by' => $createdBy,
                'created_by_name' => (string) ($adminNameMap[$createdBy] ?? ''),
                'executed_at' => $this->nullableDateTime($row['executed_at'] ?? null),
                'finished_at' => $this->nullableDateTime($row['finished_at'] ?? null),
                'created_at' => $this->nullableDateTime($row['created_at'] ?? null) ?? '',
                'updated_at' => $this->nullableDateTime($row['updated_at'] ?? null) ?? '',
            ];
        }, $rows);

        return $this->succeed([
            'data' => $data,
            'total_count' => $totalCount,
        ]);
    }

    #[BodyParameter(name: 'id', description: '批次任务 ID，对应 funding_operation.id', type: 'integer', required: true)]
    #[BodyParameter(name: 'activity_name', description: '活动名称；未在导入阶段固化时，执行前可补传', type: 'string', required: false)]
    #[BodyParameter(name: 'reason', description: '内部原因描述；未在导入阶段固化时，执行前可补传', type: 'string', required: false)]
    #[Response(
        description: '执行批量处理。接口会对批次状态、可执行明细和并发重复提交做校验，通过后原子切换到执行中状态。',
        type: 'array{id:int,status:int,status_text:string,activity_name:string,reason:string,executed_at:string,message:string}',
        examples: [[
            'id' => 101,
            'status' => 1,
            'status_text' => '执行中',
            'activity_name' => '五月回馈活动',
            'reason' => '活动补发',
            'executed_at' => '2026-05-15 10:05:00',
            'message' => '批量任务已提交队列，正在执行中',
        ]]
    )]
    /**
     * 批量操作-执行批量处理
     *
     * Body:
     * - id:int 必填，批次任务 ID
     * - activity_name:string 可选，活动名称
     * - reason:string 可选，内部原因描述
     *
     * Response.fields:
     * - id:int 批次任务 ID
     * - status:int 批次当前状态；执行接口返回后通常为 1=执行中
     * - status_text:string 状态文案
     * - activity_name:string 活动名称回显
     * - reason:string 内部原因描述回显
     * - executed_at:string 开始执行时间
     * - message:string 前端展示提示
     *
     * @param Request $request request
     *
     * @return JsonResponse
     */
    public function execute(WalletImportExecuteRequest $request): JsonResponse
    {
        $jobId = (int) $request->input('id');
        $activityName = trim((string) $request->input('activity_name', ''));
        $reason = trim((string) $request->input('reason', ''));
        $executeService = app(WalletImportExecuteService::class);

        try {
            $result = $executeService->dispatchExecute($jobId, $activityName, $reason);
            try {
                WalletImportExecuteJob::dispatch($jobId);
            } catch (\Throwable $dispatchException) {
                $executeService->revertDispatchedState($jobId);
                throw $dispatchException;
            }
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage() !== '' ? $e->getMessage() : trans('public.message.state_error'));
        }

        return $this->succeed([
            ...$result,
            'message' => '批量任务已提交执行，状态已切换为执行中',
        ]);
    }

    private function ledgerTypeText(int $fundType): string
    {
        $type = TypeLedger::tryFrom($fundType);
        if ($type === null) {
            return '';
        }

        if (method_exists($type, 'label')) {
            return (string) $type->label();
        }

        if (method_exists($type, 'name')) {
            return (string) $type->name();
        }

        return (string) $type->name;
    }

    private function directionText(int $direction): string
    {
        return match ($direction) {
            FundingLedger::DIRECTION_IN => '发放',
            FundingLedger::DIRECTION_OUT => '扣除',
            default => '',
        };
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

    private function normalizeDateBoundary(string $value, bool $isStart): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return $value;
        }

        return $isStart ? ($value . ' 00:00:00') : ($value . ' 23:59:59');
    }

    /**
     * @return array<int>
     */
    private function walletImportListStatuses(): array
    {
        return [
            FundingOperation::STATUS_PROCESSING,
            FundingOperation::STATUS_PARTIAL_SUCCESS,
            FundingOperation::STATUS_SUCCESS,
            FundingOperation::STATUS_FAIL,
            FundingOperation::STATUS_CANCELLED,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function importTemplateHeaders(bool $isBonus): array
    {
        $headers = [
            self::HEADER_USER_CODE,
            self::HEADER_AMOUNT,
            self::HEADER_REASON,
        ];

        if ($isBonus) {
            $headers[] = self::HEADER_ACTIVITY_NAME;
        }

        return $headers;
    }

    /**
     * @return array<int, string>
     */
    private function importTemplateExampleRow(bool $isBonus): array
    {
        $row = [
            '1000001',
            '1.000000',
            '原因描述示例（必填）',
        ];

        if ($isBonus) {
            $row[] = '活动名称示例（赠金必填）';
        }

        return $row;
    }

    private function importTemplateFormatText(bool $isBonus): string
    {
        $headers = [
            self::HEADER_USER_CODE . '（必填）',
            self::HEADER_AMOUNT . '（必填）',
            self::HEADER_REASON . '（必填）',
        ];

        if ($isBonus) {
            $headers[] = self::HEADER_ACTIVITY_NAME . '（赠金必填）';
        }

        return implode('、', $headers);
    }

    /**
     * @param array<int, array<int, array<int, mixed>>> $sheets
     */
    private function isValidImportTemplate(array $sheets, bool $isBonus): bool
    {
        $sheetRows = $sheets[0] ?? [];
        $headerRow = $sheetRows[0] ?? [];
        if (! is_array($headerRow)) {
            return false;
        }

        $expectedHeaders = $this->importTemplateHeaders($isBonus);
        if (count($headerRow) !== count($expectedHeaders)) {
            return false;
        }

        foreach ($expectedHeaders as $index => $expectedHeader) {
            if (! $this->isImportHeaderMatch(trim((string) ($headerRow[$index] ?? '')), $expectedHeader)) {
                return false;
            }
        }

        return true;
    }

    private function isImportHeaderMatch(string $actualHeader, string $expectedHeader): bool
    {
        $allowedHeaders = match ($expectedHeader) {
            self::HEADER_USER_CODE => [self::HEADER_USER_CODE, self::HEADER_USER_CODE . '（必填）'],
            self::HEADER_AMOUNT => [self::HEADER_AMOUNT, self::HEADER_AMOUNT . '（必填）'],
            self::HEADER_REASON => [self::HEADER_REASON, self::HEADER_REASON . '（必填）'],
            self::HEADER_ACTIVITY_NAME => [self::HEADER_ACTIVITY_NAME, self::HEADER_ACTIVITY_NAME . '（赠金必填）'],
            default => [$expectedHeader],
        };

        return in_array($actualHeader, $allowedHeaders, true);
    }

    /**
     * @param array<int, array<int, mixed>> $sheets
     * @return array<int, array<int, mixed>>
     */
    private function extractImportRows(array $sheets): array
    {
        $sheetRows = $sheets[0] ?? [];
        if (count($sheetRows) <= 1) {
            return [];
        }

        $dataRows = array_slice($sheetRows, 1);

        return array_values(array_filter($dataRows, static function (array $row): bool {
            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param array<int, mixed> $row
     * @return array{user_code:int,amount:string,amount_micros:int,reason:string,activity_name:string,status:int,error_message:string}
     */
    private function normalizeImportRow(array $row, bool $isBonus): array
    {
        $userCodeRaw = trim((string) ($row[0] ?? ''));
        $amountRaw = trim((string) ($row[1] ?? ''));
        $reason = trim((string) ($row[2] ?? ''));
        $activityName = trim((string) ($row[3] ?? ''));
        $errors = [];

        $userCode = ctype_digit($userCodeRaw) ? (int) $userCodeRaw : 0;
        if ($userCode <= 0) {
            $errors[] = '用户ID非法';
        }

        $amountMicros = $this->parseAmountToMicros($amountRaw);
        if ($amountMicros <= 0) {
            $errors[] = '金额必须大于0';
        }

        if ($reason === '') {
            $errors[] = '原因描述不能为空';
        }

        if ($isBonus && $activityName === '') {
            $errors[] = '活动名称不能为空';
        }

        return [
            'user_code' => $userCode,
            'amount' => $this->formatMicros($amountMicros),
            'amount_micros' => $amountMicros,
            'reason' => $reason,
            'activity_name' => $activityName,
            'status' => $errors === [] ? FundingOperationRecord::STATUS_PENDING : FundingOperationRecord::STATUS_INVALID,
            'error_message' => implode('；', $errors),
        ];
    }

    private function parseAmountToMicros(string $amount): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,6})?$/', $amount)) {
            return 0;
        }

        [$integerPart, $decimalPart] = array_pad(explode('.', $amount, 2), 2, '');
        $decimalPart = str_pad(substr($decimalPart, 0, 6), 6, '0');

        return ((int) $integerPart * 1000000) + (int) $decimalPart;
    }

    private function formatMicros(int $micros): string
    {
        $negative = $micros < 0;
        $micros = abs($micros);
        $integerPart = intdiv($micros, 1000000);
        $decimalPart = str_pad((string) ($micros % 1000000), 6, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '') . $integerPart . '.' . $decimalPart;
    }

    private function isBonusFundType(int $fundType): bool
    {
        if ($fundType === self::FUND_TYPE_BONUS) {
            return true;
        }

        $type = TypeLedger::tryFrom($fundType);
        if ($type === null) {
            return false;
        }

        if ($type->name === 'Bonus') {
            return true;
        }

        $label = '';
        if (method_exists($type, 'label')) {
            $label = (string) $type->label();
        } elseif (method_exists($type, 'name')) {
            $label = (string) $type->name();
        }

        return str_contains($label, '赠金');
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $columns
     * @return array<string, mixed>
     */
    private function filterPayloadByColumns(array $payload, array $columns): array
    {
        return array_intersect_key($payload, array_flip($columns));
    }
}
