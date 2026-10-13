<?php

namespace App\Services\Api;

use App\Models\Api\Source;
use App\Repositories\Api\IbStatSnapshotRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Scaffold\Framework\Enums\TypeIbSettlementStatus;

class IbCommissionRecordService
{
    public function __construct(
        private IbStatSnapshotRepository $ibStatSnapshotRepo,
        private IbCommissionSourceDetailFormatter $sourceDetailFormatter,
    ) {}

    public function getList(array $filters, int $limit, int $offset): array
    {
        $isExportAll = $limit < 0 || $offset < 0;
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $query = DB::connection(DB_API)
            ->table('ib_commission_record as icr')
            ->select([
                'icr.id',
                'icr.ticket',
                'icr.source',
                'icr.position_size',
                'icr.commission_amount',
                'icr.close_time',
                'icr.settled_at',
                'icr.settlement_status',
            ]);

        if ($this->hasCommissionRecordColumn('estimated_settlement_at')) {
            $query->addSelect('icr.estimated_settlement_at');
        } else {
            $query->addSelect(DB::raw('NULL as estimated_settlement_at'));
        }

        if ($this->hasCommissionRecordColumn('rejection_reason')) {
            $query->addSelect('icr.rejection_reason');
        } else {
            $query->addSelect(DB::raw('NULL as rejection_reason'));
        }

        $ticket = trim((string) ($filters['ticket'] ?? ''));
        if ($ticket !== '') {
            $query->where('icr.ticket', 'like', $this->like($ticket));
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source !== '') {
            $query->where('icr.source', $source);
        }

        if (($filters['close_time_start'] ?? '') !== '') {
            $query->where('icr.close_time', '>=', (string) $filters['close_time_start']);
        }
        if (($filters['close_time_end'] ?? '') !== '') {
            $query->where('icr.close_time', '<=', (string) $filters['close_time_end']);
        }
        if (($filters['settled_at_start'] ?? '') !== '') {
            $query->where('icr.settled_at', '>=', (string) $filters['settled_at_start']);
        }
        if (($filters['settled_at_end'] ?? '') !== '') {
            $query->where('icr.settled_at', '<=', (string) $filters['settled_at_end']);
        }

        if (($filters['settlement_status'] ?? null) !== null) {
            $query->where('icr.settlement_status', (int) $filters['settlement_status']);
        }

        $total = (clone $query)->count('icr.id');
        $query->orderByDesc('icr.id');
        if (! $isExportAll) {
            $query->offset($offset)->limit($limit);
        }
        $rows = $query->get();

        return [
            'data' => $rows->map(function ($row): array {
                $settlementStatus = (int) ($row->settlement_status ?? 0);
                $estimatedSettlementAt = $row->estimated_settlement_at ?? null;
                if (($estimatedSettlementAt === null || $estimatedSettlementAt === '') && ($row->close_time ?? null)) {
                    $estimatedSettlementAt = date('Y-m-d H:i:s', strtotime((string) $row->close_time.' +1 day'));
                }

                return [
                    'id' => (int) ($row->id ?? 0),
                    'ticket' => (string) ($row->ticket ?? ''),
                    'source' => (string) ($row->source ?? ''),
                    'position_size' => $this->formatDecimal($row->position_size ?? '0'),
                    'commission_amount' => $this->formatDecimal($row->commission_amount ?? '0'),
                    'close_time' => $this->formatDateTime($row->close_time) ?? '',
                    'estimated_settlement_at' => $this->formatDateTime($estimatedSettlementAt) ?? '',
                    'settled_at' => $this->formatDateTime($row->settled_at),
                    'rejection_reason' => ($row->rejection_reason ?? null) === '' ? null : ($row->rejection_reason ?? null),
                    'settlement_status' => $settlementStatus,
                    'settlement_status_text' => $this->settlementStatusText($settlementStatus),
                    'can_reject_settlement' => $settlementStatus === TypeIbSettlementStatus::Pending->value ? 1 : 0,
                ];
            })->all(),
            'total_count' => $total,
        ];
    }

    /**
     * 拒绝结算
     */
    public function reject(int $commissionRecordId, string $rejectionReason): void
    {
        if (! Schema::connection(DB_API)->hasTable('ib_commission_record')) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        if (! Schema::connection(DB_API)->hasColumn('ib_commission_record', 'ticket')) {
            throw new \Exception(trans('public.message.state_error'));
        }

        DB::connection(DB_API)->transaction(function () use ($commissionRecordId, $rejectionReason): void {
            $record = DB::connection(DB_API)
                ->table('ib_commission_record')
                ->where('id', $commissionRecordId)
                ->lockForUpdate()
                ->select([
                    'id',
                    'settlement_status',
                    'ticket',
                ])
                ->first();

            if (! $record) {
                throw new \Exception(trans('public.message.non_existent'));
            }

            $status = (int) ($record->settlement_status ?? 0);
            if ($status !== TypeIbSettlementStatus::Pending->value) {
                throw new \Exception(trans('public.message.state_error'));
            }

            $ticket = (string) ($record->ticket ?? '');
            if ($ticket === '') {
                throw new \Exception(trans('public.message.state_error'));
            }

            $recordPayload = [
                'settlement_status' => TypeIbSettlementStatus::Rejected->value,
            ];
            if ($this->hasCommissionRecordColumn('rejection_reason')) {
                $recordPayload['rejection_reason'] = $rejectionReason;
            }

            $updated = DB::connection(DB_API)
                ->table('ib_commission_record')
                ->where('id', $commissionRecordId)
                ->update($recordPayload);
            if ($updated <= 0) {
                throw new \Exception(trans('public.message.state_error'));
            }

            if (! Schema::connection(DB_API)->hasTable('ib_commission_settlement')) {
                return;
            }

            if (! Schema::connection(DB_API)->hasColumn('ib_commission_settlement', 'ticket')) {
                return;
            }

            $settlementPayload = [
                'settlement_status' => TypeIbSettlementStatus::Rejected->value,
            ];
            if (Schema::connection(DB_API)->hasColumn('ib_commission_settlement', 'rejection_reason')) {
                $settlementPayload['rejection_reason'] = $rejectionReason;
            }

            DB::connection(DB_API)
                ->table('ib_commission_settlement')
                ->where('ticket', $ticket)
                ->where('settlement_status', TypeIbSettlementStatus::Pending->value)
                ->update($settlementPayload);
        });
    }

    /**
     * 基于佣金结算数据的佣金分配详情
     *
     * @param array{
     *     user_code?: int|null,
     *     level_id?: int|null
     * } $filters
     * @return array{data: array<int, array>, total_count: int}
     */
    public function getAllocations(int $commissionRecordId, array $filters, int $limit, int $offset): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        if (! Schema::connection(DB_API)->hasTable('ib_commission_record')
            || ! Schema::connection(DB_API)->hasTable('ib_commission_settlement')
        ) {
            return [
                'data' => [],
                'total_count' => 0,
            ];
        }

        if (! Schema::connection(DB_API)->hasColumn('ib_commission_record', 'ticket')) {
            return [
                'data' => [],
                'total_count' => 0,
            ];
        }

        $record = DB::connection(DB_API)
            ->table('ib_commission_record')
            ->where('id', $commissionRecordId)
            ->select(['ticket'])
            ->first();
        $ticket = $record ? (string) ($record->ticket ?? '') : '';
        if ($ticket === '') {
            return [
                'data' => [],
                'total_count' => 0,
            ];
        }

        if (! Schema::connection(DB_API)->hasColumn('ib_commission_settlement', 'ticket')) {
            return [
                'data' => [],
                'total_count' => 0,
            ];
        }

        $userCodeColumn = $this->resolveFirstExistingColumn('ib_commission_settlement', [
            'ib_user_code',
            'user_code',
        ]) ?? 'user_code';

        $levelIdColumn = $this->resolveFirstExistingColumn('ib_commission_settlement', [
            'level_id',
            'ib_level_id',
        ]) ?? 'level_id';

        $commissionAmountColumn = $this->resolveFirstExistingColumn('ib_commission_settlement', [
            'commission_amount',
            'downstream_commission_amount',
            'settlement_amount',
        ]) ?? 'commission_amount';

        $query = DB::connection(DB_API)
            ->table('ib_commission_settlement as ica')
            ->leftJoin('ib_level as il', 'il.id', '=', "ica.$levelIdColumn")
            ->where('ica.ticket', $ticket)
            ->select([
                DB::raw("ica.$userCodeColumn as user_code"),
                DB::raw("ica.$levelIdColumn as level_id"),
                DB::raw("ica.$commissionAmountColumn as commission_amount"),
                DB::raw('il.name as level_name'),
            ]);

        if (($filters['user_code'] ?? null) !== null) {
            $query->where("ica.$userCodeColumn", (int) $filters['user_code']);
        }

        if (($filters['level_id'] ?? null) !== null) {
            $query->where("ica.$levelIdColumn", (int) $filters['level_id']);
        }

        $total = (clone $query)->count(DB::raw("ica.$userCodeColumn"));
        $rows = $query->orderByDesc(DB::raw("ica.$userCodeColumn"))
            ->offset($offset)
            ->limit($limit)
            ->get();

        $lang = (string) (app()->getLocale() ?: config('app.locale', 'en_US'));

        return [
            'data' => $rows->map(function ($row) use ($lang): array {
                return [
                    'user_code' => (int) ($row->user_code ?? 0),
                    'level_id' => (int) ($row->level_id ?? 0),
                    'level_name' => $this->resolveLocalizedName($row->level_name ?? null, $lang),
                    'commission_amount' => $this->formatDecimal($row->commission_amount ?? '0'),
                ];
            })->all(),
            'total_count' => $total,
        ];
    }

    /**
     * 获取交易品种下拉选项
     *
     * 数据来源:
     * - 仅读取 ts_source 表，不依赖用户交易数据
     *
     * 当 $withConfig = true 时，返回完整配置信息（包含 source_status 上下架状态等字段），
     * 内部复用 IbCommissionSourceDetailFormatter::formatDefault 格式化。
     *
     * @param  bool  $withConfig  是否包含完整配置信息
     * @return array<int, array<string, mixed>>
     */
    public function getSourceOptions(bool $withConfig = false): array
    {
        if ($withConfig) {
            $lang = (string) (app()->getLocale() ?: config('app.locale', 'en_US'));

            return Source::query()
                ->whereNotNull('source')
                ->where('source', '!=', '')
                ->orderBy('source')
                ->get(['source', 'name', 'config', 'status'])
                ->unique('source')
                ->values()
                ->map(function (Source $source) use ($lang): array {
                    $detail = $this->sourceDetailFormatter->formatDefault($source, $lang);
                    $detail['commission_amount'] = '';
                    $detail['commission_fee_rate'] = '';

                    return [
                        'key' => $detail['source'],
                        'value' => $detail['source'],
                        ...$detail,
                    ];
                })
                ->all();
        }

        $sources = DB::connection(DB_API)
            ->table('ts_source')
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->orderBy('source')
            ->distinct()
            ->pluck('source')
            ->map(static fn ($source): string => (string) $source)
            ->all();

        return array_map(static fn (string $source): array => [
            'key' => $source,
            'value' => $source,
        ], $sources);
    }

    /**
     * 基于统计快照的佣金统计
     *
     * @param array{
     *     user_code: int
     * } $filters
     * @return array{settled_commission_amount:string,pending_commission_amount:string}
     */
    public function getStatistics(array $filters): array
    {
        $info = $this->ibStatSnapshotRepo->getInfo($filters['user_code']);
        if (! $info) {
            return [];
        }

        return [
            'settled_commission_amount' => $info->settled_commission_amount,
            'pending_commission_amount' => $info->pending_commission_amount,
        ];
    }

    private function settlementStatusText(int $status): string
    {
        return match ($status) {
            TypeIbSettlementStatus::Settled->value => '已结算',
            TypeIbSettlementStatus::Rejected->value => '已拒绝',
            TypeIbSettlementStatus::PartialSettled->value => '部分结算',
            TypeIbSettlementStatus::Failed->value => '结算失败',
            default => '未结算',
        };
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function formatDecimal(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $string = (string) $value;
        if (str_contains($string, '.')) {
            $string = rtrim(rtrim($string, '0'), '.');
        }

        return $string === '' ? '0' : $string;
    }

    private function like(string $value): string
    {
        $escaped = addcslashes($value, '%_');

        return sprintf('%%%s%%', $escaped);
    }

    private function hasCommissionRecordColumn(string $column): bool
    {
        static $columnExistence = [];
        if (array_key_exists($column, $columnExistence)) {
            return $columnExistence[$column];
        }

        $columnExistence[$column] = Schema::connection(DB_API)->hasColumn('ib_commission_record', $column);

        return $columnExistence[$column];
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function resolveFirstExistingColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if (Schema::connection(DB_API)->hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>|string|null  $value
     */
    private function resolveLocalizedName(array|string|null $value, string $lang): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return '';
        }

        return (string) ($value[$lang] ?? $value['en_US'] ?? reset($value) ?: '');
    }
}
