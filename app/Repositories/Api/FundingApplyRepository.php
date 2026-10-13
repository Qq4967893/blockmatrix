<?php

namespace App\Repositories\Api;

use App\Http\Requests\DataOverview\DataOverviewRequest;
use App\Models\Api\FundingApply;
use App\Models\Api\UserProhibition;
use App\Models\Api\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Scaffold\Funding\Enums\FundingApplyStatus;
use Scaffold\Funding\Enums\FundingFinanceStatus;
use Scaffold\Framework\Repositories\Repository;

class FundingApplyRepository extends Repository
{
    public function __construct(FundingApply $model)
    {
        parent::__construct($model);
    }

    public function findWithdrawalByOrderNo(int $orderNo): ?array
    {
        $record = $this->withdrawalByOrderNoQuery($orderNo)
            ->useWritePdo()
            ->first($this->withdrawalAuditColumns());

        if (empty($record)) {
            return null;
        }

        return (array) $record;
    }

    public function lockWithdrawalByOrderNo(int $orderNo): ?array
    {
        $record = $this->withdrawalByOrderNoQuery($orderNo)
            ->useWritePdo()
            ->lockForUpdate()
            ->first($this->withdrawalAuditColumns());

        return empty($record) ? null : (array) $record;
    }

    public function lockWithdrawalUser(int $userCode): void
    {
        DB::connection(DB_API)
            ->table('user')
            ->useWritePdo()
            ->where('user_code', $userCode)
            ->lockForUpdate()
            ->value('user_code');
    }

    public function resetFailedWithdrawalForRiskRetry(int $orderNo): int
    {
        return DB::connection(DB_API)
            ->table('funding_apply')
            ->where('order_no', $orderNo)
            ->where('direction', FundingApply::DIRECTION_WITHDRAW)
            ->where('status', FundingApply::STATUS_FAILED)
            ->where('risk_status', FundingApply::withdrawalRiskStatusMap()['approved'])
            ->where('finance_status', FundingApply::withdrawalFinanceStatusMap()['failed'])
            ->update([
                'status' => FundingApply::STATUS_PENDING,
                'risk_status' => FundingApply::withdrawalRiskStatusMap()['pending'],
                'finance_status' => null,
                'risk_audited_by' => null,
                'risk_audited_at' => null,
                'risk_reason' => null,
                'finance_audited_by' => null,
                'finance_audited_at' => null,
                'finance_reason' => null,
                'effective_at' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * 扫描处于自动重试窗口的出金单：状态过滤 + JSON 预过滤 + 排除出金限制用户，精确到期判定由服务层完成。
     *
     * @return array<int, array{id:int, order_no:string, user_code:int, reference_content:?string}>
     */
    public function getWithdrawalRetryWindowCandidates(int $limit): array
    {
        return DB::connection(DB_API)
            ->table('funding_apply')
            ->useWritePdo()
            ->where('direction', FundingApply::DIRECTION_WITHDRAW)
            ->where('status', FundingApplyStatus::Processing->value)
            ->where('risk_status', FundingApply::withdrawalRiskStatusMap()['approved'])
            ->where('finance_status', FundingFinanceStatus::Pending->value)
            ->where('reference_content', 'like', '%"retry_phase":"retrying"%')
            ->where('reference_content', 'like', sprintf('%%"last_error_code":"%s"%%', FundingApply::WALLET_STATUS_NOT_SUBMITTED))
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('user_prohibition')
                    ->whereColumn('user_prohibition.user_code', 'funding_apply.user_code')
                    ->where('user_prohibition.type', UserProhibition::TYPE_WITHDRAW);
            })
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get(['id', 'order_no', 'user_code', 'reference_content'])
            ->map(static fn ($item): array => (array) $item)
            ->all();
    }

    /**
     * 锁内合并写入 reference_content.wallet_dispatch，保留其余键；调用方必须先锁定申请单行。
     *
     * @param array<string, mixed> $walletDispatch
     */
    public function mergeWithdrawalWalletDispatch(int $orderNo, array $walletDispatch): void
    {
        $record = DB::connection(DB_API)
            ->table('funding_apply')
            ->useWritePdo()
            ->where('direction', FundingApply::DIRECTION_WITHDRAW)
            ->where('order_no', $orderNo)
            ->first(['reference_content']);

        $decoded = json_decode((string) ($record->reference_content ?? ''), true);
        $referenceContent = is_array($decoded) ? $decoded : [];
        $referenceContent['wallet_dispatch'] = $walletDispatch;

        DB::connection(DB_API)
            ->table('funding_apply')
            ->where('direction', FundingApply::DIRECTION_WITHDRAW)
            ->where('order_no', $orderNo)
            ->update([
                'reference_content' => json_encode($referenceContent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
    }

    /**
     * 按用户汇总成功充值、提现的实际到账金额。
     *
     * @return array{deposit: float, withdraw: float}
     */
    public function getSuccessfulAmountTotalsByUserCode(int $userCode): array
    {
        $totals = $this->getQuery()
            ->from('funding_apply')
            ->where('user_code', $userCode)
            ->where('status', FundingApply::STATUS_APPROVED)
            ->whereIn('direction', [
                FundingApply::DIRECTION_DEPOSIT,
                FundingApply::DIRECTION_WITHDRAW,
            ])
            ->groupBy('direction')
            ->selectRaw('direction, SUM(net_amount) as total_amount')
            ->pluck('total_amount', 'direction')
            ->toArray();

        return [
            'deposit'  => (float) ($totals[FundingApply::DIRECTION_DEPOSIT] ?? 0),
            'withdraw' => abs((float) ($totals[FundingApply::DIRECTION_WITHDRAW] ?? 0)),
        ];
    }

    /**
     * 查询指定时间范围内成功入金/出金的金额统计
     *
     * 统计条件：
     * - user.type 为真实用户或黑名单用户
     * - funding_apply.status 为成功（STATUS_APPROVED）
     * - funding_apply.effective_at（到账时间）在查询时间范围内
     * - 按 direction 区分入金（DIRECTION_DEPOSIT）/ 出金（DIRECTION_WITHDRAW），金额取 net_amount（实际到账），出金取绝对值
     * - net_deposit_amount = deposit_amount - withdrawal_amount
     *
     * @param string $startDateTime 开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime   结束时间，格式 Y-m-d H:i:s
     * @param string $customerType  客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     *
     * @return array{deposit_amount:float, withdrawal_amount:float, net_deposit_amount:float}
     */
    public function getApprovedAmountTotals(string $startDateTime, string $endDateTime, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL): array
    {
        $query = DB::connection(DB_API)
            ->table('funding_apply as fa')
            ->join('user as u', 'u.user_code', '=', 'fa.user_code')
            ->whereIn('u.type', [User::TYPE_REAL, User::TYPE_BLACK])
            ->where('fa.status', FundingApply::STATUS_APPROVED)
            ->whereIn('fa.direction', [FundingApply::DIRECTION_DEPOSIT, FundingApply::DIRECTION_WITHDRAW])
            ->where('fa.effective_at', '>=', $startDateTime)
            ->where('fa.effective_at', '<=', $endDateTime);

        if ($customerType == DataOverviewRequest::CUSTOMER_TYPE_AGENT) {
            $query->whereExists(function ($subQuery) {
                $subQuery->selectRaw(1)
                    ->from('ib_account as ia')
                    ->whereColumn('ia.user_code', 'u.user_code');
            });
        }

        $totals = $query
            ->groupBy('fa.direction')
            ->selectRaw('fa.direction, COALESCE(SUM(fa.net_amount), 0) as total_amount')
            ->pluck('total_amount', 'fa.direction');

        $depositAmount    = round((float) ($totals[FundingApply::DIRECTION_DEPOSIT] ?? 0), 2);
        $withdrawalAmount = round(abs((float) ($totals[FundingApply::DIRECTION_WITHDRAW] ?? 0)), 2);

        return [
            'deposit_amount'    => $depositAmount,
            'withdrawal_amount' => $withdrawalAmount,
            'net_deposit_amount' => round($depositAmount - $withdrawalAmount, 2),
        ];
    }

    /**
     * 查询指定时间范围内按日期粒度分组成功入金/出金的金额统计
     *
     * 统计口径与 getApprovedAmountTotals 一致，按 fa.effective_at 的日/周（周一起始）/月与 direction 分组，
     * net_deposit_amount = deposit_amount - withdrawal_amount。
     *
     * @param string $startDateTime 开始时间，格式 Y-m-d H:i:s
     * @param string $endDateTime   结束时间，格式 Y-m-d H:i:s
     * @param string $customerType  客户类型：all（全部）/ ib（代理客户，user_code 存在于 ib_account 表）
     * @param string $dateType      日期粒度：day / week / month
     *
     * @return array<string, array{deposit_amount:float, withdrawal_amount:float, net_deposit_amount:float}> key: 期间起始日期（Y-m-d）
     */
    public function getApprovedAmountTotalsByPeriod(string $startDateTime, string $endDateTime, string $customerType = DataOverviewRequest::CUSTOMER_TYPE_ALL, string $dateType = DataOverviewRequest::CHART_DATE_TYPE_DAY): array
    {
        $query = DB::connection(DB_API)
            ->table('funding_apply as fa')
            ->join('user as u', 'u.user_code', '=', 'fa.user_code')
            ->whereIn('u.type', [User::TYPE_REAL, User::TYPE_BLACK])
            ->where('fa.status', FundingApply::STATUS_APPROVED)
            ->whereIn('fa.direction', [FundingApply::DIRECTION_DEPOSIT, FundingApply::DIRECTION_WITHDRAW])
            ->where('fa.effective_at', '>=', $startDateTime)
            ->where('fa.effective_at', '<=', $endDateTime);

        if ($customerType == DataOverviewRequest::CUSTOMER_TYPE_AGENT) {
            $query->whereExists(function ($subQuery) {
                $subQuery->selectRaw(1)
                    ->from('ib_account as ia')
                    ->whereColumn('ia.user_code', 'u.user_code');
            });
        }

        $rows = $query
            ->selectRaw(self::periodExpression('fa.effective_at', $dateType) . ' as stat_date, fa.direction, COALESCE(SUM(fa.net_amount), 0) as total_amount')
            ->groupBy('stat_date', 'fa.direction')
            ->get();

        $daily = [];
        foreach ($rows as $row) {
            $date = (string) $row->stat_date;
            $daily[$date] ??= ['deposit_amount' => 0.0, 'withdrawal_amount' => 0.0, 'net_deposit_amount' => 0.0];

            if ((int) $row->direction === FundingApply::DIRECTION_DEPOSIT) {
                $daily[$date]['deposit_amount'] = round((float) $row->total_amount, 2);
            } else {
                $daily[$date]['withdrawal_amount'] = round(abs((float) $row->total_amount), 2);
            }
        }

        foreach ($daily as $date => $totals) {
            $daily[$date]['net_deposit_amount'] = round($totals['deposit_amount'] - $totals['withdrawal_amount'], 2);
        }

        return $daily;
    }

    /**
     * 生成按日/周/月分组的日期表达式，返回期间起始日期
     *
     * @param string $column 日期列（可带表别名）
     * @param string $dateType 日期粒度：day / week / month
     */
    private static function periodExpression(string $column, string $dateType): string
    {
        return match ($dateType) {
            DataOverviewRequest::CHART_DATE_TYPE_WEEK  => "DATE_SUB(DATE({$column}), INTERVAL WEEKDAY({$column}) DAY)",
            DataOverviewRequest::CHART_DATE_TYPE_MONTH => "DATE_FORMAT({$column}, '%Y-%m-01')",
            default                                    => "DATE({$column})",
        };
    }

    public function findWithdrawalById(int $id): ?array
    {
        $record = DB::connection(DB_API)
            ->table('funding_apply as fa')
            ->where('fa.direction', FundingApply::DIRECTION_WITHDRAW)
            ->where('fa.id', $id)
            ->first([
                'fa.id',
                'fa.order_no',
                'fa.user_code',
                'fa.platform',
            ]);

        if (empty($record)) {
            return null;
        }

        return (array) $record;
    }

    /**
     * @param array{
     *     user_code?: int|null,
     *     direction?: int|null,
     *     status?: int|null,
     *     deposit_status?: string|null,
     *     received_time_start?: string|null,
     *     received_time_end?: string|null,
     *     platform?: string|null
     * } $filters
     * @return array{0: array<int, array>, 1: int}
     */
    public function paginateDeposit(array $filters, int $limit, int $offset): array
    {
        $query = $this->baseQuery()
            ->whereIn('fa.status', FundingApply::DEPOSIT_STATUS_OPTIONS);

        if (($filters['direction'] ?? null) !== null && $filters['direction'] !== '') {
            $direction = (int) $filters['direction'];
            if (in_array($direction, FundingApply::DEPOSIT_DIRECTION_OPTIONS, true)) {
                $query->where('fa.direction', $direction);
            }
        }

        if (!empty($filters['user_code'])) {
            $query->where('fa.user_code', (int) $filters['user_code']);
        }

        if ($filters['status'] !== null && $filters['status'] !== '') {
            $status = (int) $filters['status'];
            if ($status === FundingApply::STATUS_FAILED) {
                $query->where('fa.user_code', 0);
            } elseif (in_array($status, FundingApply::DEPOSIT_STATUS_OPTIONS, true)) {
                $query->where('fa.status', $status);
                $query->where('fa.user_code', '!=', 0);
            }
        }

        if (!empty($filters['deposit_status'])) {
            $successfulDepositCountSub = '(SELECT COUNT(fa2.id) FROM funding_apply AS fa2 WHERE fa2.user_code = fa.user_code AND fa2.direction = ? AND fa2.status = ?)';
            $query->where('fa.status', FundingApply::STATUS_APPROVED);
            $query->where('fa.user_code', '!=', 0);
            if ($filters['deposit_status'] === FundingApply::DEPOSIT_STATUS_FIRST_TIME) {
                $query->whereRaw($successfulDepositCountSub . ' = 1', [FundingApply::DIRECTION_DEPOSIT, FundingApply::STATUS_APPROVED]);
            } elseif ($filters['deposit_status'] === FundingApply::DEPOSIT_STATUS_MULTIPLE) {
                $query->whereRaw($successfulDepositCountSub . ' > 1', [FundingApply::DIRECTION_DEPOSIT, FundingApply::STATUS_APPROVED]);
            }
        }

        if (!empty($filters['received_time_start'])) {
            $query->where('fa.effective_at', '>=', $filters['received_time_start']);
        }

        if (!empty($filters['received_time_end'])) {
            $query->where('fa.effective_at', '<=', $filters['received_time_end']);
        }

        if (($filters['platform'] ?? null) !== null && $filters['platform'] !== '') {
            $query->where('fa.platform', (string) $filters['platform']);
        }

        return $this->paginateQuery($query, $limit, $offset);
    }

    /**
     * @param array{
     *     user_code?: int|null,
     *     order_no?: int|string|null,
     *     status?: int|null,
     *     withdraw_time_start?: string|null,
     *     withdraw_time_end?: string|null,
     *     platform?: string|null
     * } $filters
     * @return array{0: array<int, array>, 1: int}
     */
    public function paginateWithdrawalRisk(array $filters, int $limit, int $offset): array
    {
        $query = $this->baseQuery()
            ->where('fa.direction', FundingApply::DIRECTION_WITHDRAW)
            ->where('fa.status', '!=', FundingApply::STATUS_CANCEL)
            ->whereIn('fa.risk_status', array_values(FundingApply::withdrawalRiskStatusMap()));

        $this->applyWithdrawalCommonFilters($query, $filters);

        if ($filters['status'] !== null && $filters['status'] !== '') {
            $query->where('fa.risk_status', (int) $filters['status']);
        }

        return $this->paginateQuery($query, $limit, $offset, true);
    }

    /**
     * @param array{
     *     user_code?: int|null,
     *     order_no?: int|string|null,
     *     status?: int|null,
     *     withdraw_time_start?: string|null,
     *     withdraw_time_end?: string|null,
     *     platform?: string|null
     * } $filters
     * @return array{0: array<int, array>, 1: int}
     */
    public function paginateWithdrawalFinance(array $filters, int $limit, int $offset): array
    {
        $query = $this->baseQuery()
            ->where('fa.direction', FundingApply::DIRECTION_WITHDRAW)
            ->where('fa.status', '!=', FundingApply::STATUS_CANCEL)
            ->whereIn('fa.finance_status', array_values(FundingApply::withdrawalFinanceStatusMap()));

        $this->applyWithdrawalCommonFilters($query, $filters);

        if ($filters['status'] !== null && $filters['status'] !== '') {
            $query->where('fa.finance_status', (int) $filters['status']);
        }

        return $this->paginateQuery($query, $limit, $offset);
    }

    /**
     * @return Builder
     */
    private function baseQuery(): Builder
    {
        return DB::connection(DB_API)
            ->table('funding_apply as fa')
            ->leftJoin('user as u', 'u.user_code', '=', 'fa.user_code');
    }

    private function applyWithdrawalCommonFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['user_code'])) {
            $query->where('fa.user_code', (int) $filters['user_code']);
        }

        if (!empty($filters['order_no'])) {
            $query->where('fa.order_no', (string) $filters['order_no']);
        }

        if (($filters['platform'] ?? null) !== null && $filters['platform'] !== '') {
            $query->where('fa.platform', (string) $filters['platform']);
        }

        if (!empty($filters['withdraw_time_start'])) {
            $query->where('fa.created_at', '>=', $filters['withdraw_time_start']);
        }

        if (!empty($filters['withdraw_time_end'])) {
            $query->where('fa.created_at', '<=', $filters['withdraw_time_end']);
        }
    }

    /**
     * @return array{0: array<int, array>, 1: int}
     */
    private function paginateQuery(
        Builder $query,
        int $limit,
        int $offset,
        bool $includeDailyWithdrawalTotals = false
    ): array
    {
        $count = (clone $query)->count('fa.id');

        $query->orderByDesc('fa.id');
        if ($limit !== -1 && $offset !== -1) {
            $query->offset($offset)->limit($limit);
        }

        $columns = [
            'fa.id',
            'fa.order_no',
            'fa.user_code',
            'fa.trade_account_id',
            'fa.direction',
            'fa.fund_type',
            'fa.apply_amount',
            'fa.fee_amount',
            'fa.net_amount',
            'fa.currency',
            'fa.rate',
            'fa.pay_amount',
            'fa.pay_currency',
            'fa.pay_rate',
            'fa.platform',
            'fa.status',
            'fa.order_id',
            'fa.reference_content',
            'fa.effective_at',
            'fa.withdraw_to',
            'fa.risk_status',
            'fa.finance_status',
            'fa.risk_audited_by',
            'fa.finance_audited_by',
            'fa.risk_reason',
            'fa.finance_reason',
            'fa.created_at',
            'fa.updated_at',
            'u.email',
            'u.type as user_type',
        ];

        if ($includeDailyWithdrawalTotals) {
            $dailyWithdrawalScope = sprintf(
                'fa_daily.user_code = fa.user_code AND fa_daily.direction = %d AND DATE(fa_daily.created_at) = DATE(fa.created_at) AND (fa_daily.id = fa.id OR fa_daily.status = %d)',
                FundingApply::DIRECTION_WITHDRAW,
                FundingApply::STATUS_APPROVED
            );

            $columns[] = DB::raw(sprintf(
                '(SELECT COUNT(fa_daily.id) FROM funding_apply AS fa_daily WHERE %s) AS daily_withdraw_count',
                $dailyWithdrawalScope
            ));
            $columns[] = DB::raw(sprintf(
                '(SELECT COALESCE(SUM(fa_daily.apply_amount), 0) FROM funding_apply AS fa_daily WHERE %s) AS daily_total_withdraw_amount',
                $dailyWithdrawalScope
            ));
        }

        $records = $query->get($columns)
            ->map(static fn($item) => (array) $item)
            ->all();

        return [$records, $count];
    }

    /**
     * 批量查询指定用户的成功入金次数。
     *
     * @param int[] $userCodes
     * @return array<int, int> key: user_code, value: 成功入金次数
     */
    public function getSuccessfulDepositCounts(array $userCodes): array
    {
        if (empty($userCodes)) {
            return [];
        }

        $rows = $this->getQuery()
            ->from('funding_apply')
            ->select('user_code', DB::raw('COUNT(id) as successful_count'))
            ->whereIn('user_code', $userCodes)
            ->where('direction', FundingApply::DIRECTION_DEPOSIT)
            ->where('status', FundingApply::STATUS_APPROVED)
            ->groupBy('user_code')
            ->pluck('successful_count', 'user_code')
            ->toArray();

        return array_map('intval', $rows);
    }

    private function withdrawalByOrderNoQuery(int $orderNo): Builder
    {
        return DB::connection(DB_API)
            ->table('funding_apply as fa')
            ->leftJoin('user as u', 'u.user_code', '=', 'fa.user_code')
            ->where('fa.direction', FundingApply::DIRECTION_WITHDRAW)
            ->where('fa.order_no', $orderNo);
    }

    /**
     * @return string[]
     */
    private function withdrawalAuditColumns(): array
    {
        return [
            'fa.id',
            'fa.order_no',
            'fa.user_code',
            'fa.trade_account_id',
            'fa.platform',
            'fa.status',
            'fa.risk_status',
            'fa.finance_status',
            'fa.currency',
            'fa.apply_amount',
            'fa.net_amount',
            'fa.rate',
            'fa.pay_currency',
            'fa.order_id',
            'fa.withdraw_to',
            'fa.reference_content',
            'fa.effective_at',
            'fa.created_at',
            'fa.updated_at',
            'u.type as user_type',
        ];
    }
}
