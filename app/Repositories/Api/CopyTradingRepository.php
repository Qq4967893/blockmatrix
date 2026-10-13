<?php

namespace App\Repositories\Api;

use App\Models\Api\MasterAccount;
use App\Models\Api\MasterLevel as ApiMasterLevel;
use App\Models\TradingSystem\Copy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Scaffold\Framework\Enums\TypeTradeAccount;
use Scaffold\Framework\Models\Api\MasterApplication;
use App\Models\Api\MasterCommissionRecord;
use Scaffold\Framework\Models\Api\MasterLevel;
use Scaffold\Framework\Models\Api\MasterStatusChangeLog;
use Scaffold\Framework\Models\TradingSystem\Order;

class CopyTradingRepository
{
    /**
     * @return array<int>
     */
    private function masterDisplayTradeAccountTypes(): array
    {
        return [
            TypeTradeAccount::UTA->value,
            TypeTradeAccount::BonusFund->value,
        ];
    }

    private function applyMasterDisplayTradeAccountTypeFilter(mixed $query, string $column, mixed $filter): void
    {
        $allowedTypes = $this->masterDisplayTradeAccountTypes();
        $query->whereIn($column, $allowedTypes);

        if ($filter === null || $filter === '') {
            return;
        }

        $tradeAccountType = (int) $filter;
        if (in_array($tradeAccountType, $allowedTypes, true)) {
            $query->where($column, $tradeAccountType);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * @param array{
     *   master_user_code?: int|string|null,
     *   master_trade_account_id?: int|string|null,
     *   master_trade_account_type?: int|string|null,
     *   follower_user_code?: int|string|null,
     *   follow_status?: int|string|null,
     *   master_trade_account_type?: int|string|null,
     *   master_trade_account_id?: int|string|null,
     *   copy_start_time_start?: string|null,
     *   copy_start_time_end?: string|null,
     *   copy_end_time_start?: string|null,
     *   copy_end_time_end?: string|null
     * } $filters
     * @return array{0: array<int, object>, 1: int}
     */
    public function getFollowRelations(array $filters, int $limit, int $offset): array
    {
        return $this->getFollowRowsFromHistory($filters, $limit, $offset);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: array<int, object>, 1: int}
     */
    private function getFollowRowsFromHistory(array $filters, int $limit, int $offset): array
    {
        $masterTradeAccountCol = $this->resolveFirstExistingColumn(DB_TS, 'copy_history', ['login_master', 'master_login', 'master_account', 'master_trade_account_id']);
        $followerTradeAccountCol = $this->resolveFirstExistingColumn(DB_TS, 'copy_history', ['login_follower', 'follower_login', 'follower_account', 'follower_trade_account_id']);
        $investAmountCol = $this->resolveFirstExistingColumn(DB_TS, 'copy_history', ['amount', 'margin_order', 'invest_amount', 'copy_amount', 'init_margin']);
        $createdAtCol = $this->resolveFirstExistingColumn(DB_TS, 'copy_history', ['created_at', 'copy_start_time', 'op_time']);
        $opTimeCol = $this->resolveFirstExistingColumn(DB_TS, 'copy_history', ['op_time', 'copy_end_time', 'close_time', 'updated_at']);
        $statusCol = $this->resolveFirstExistingColumn(DB_TS, 'copy_history', ['status', 'follow_status']);

        if ($masterTradeAccountCol === null || $followerTradeAccountCol === null || $createdAtCol === null || $opTimeCol === null || $statusCol === null) {
            return [[], 0];
        }

        $endTimeExpr = "CASE WHEN h.$statusCol = 1 THEN h.$opTimeCol ELSE NULL END";
        $investAmountExpr = $investAmountCol ? "h.$investAmountCol" : '0';

        $query = DB::connection(DB_TS)
            ->table('copy_history as h')
            ->leftJoin(DB::raw(DB_API.'.trade_account as ta_master'), 'ta_master.trade_account_id', '=', "h.$masterTradeAccountCol")
            ->leftJoin(DB::raw(DB_API.'.trade_account as ta_follower'), 'ta_follower.trade_account_id', '=', "h.$followerTradeAccountCol")
            ->leftJoin(DB::raw(DB_API.'.master_account as ma'), 'ma.trade_account_id', '=', "h.$masterTradeAccountCol")
            ->leftJoin(DB::raw(DB_API.'.master_level as ml'), 'ml.id', '=', 'ma.level_id')
            ->select([
                DB::raw("COALESCE(ta_master.user_code, h.$masterTradeAccountCol, 0) as master_user_code"),
                DB::raw("COALESCE(ta_follower.user_code, h.$followerTradeAccountCol, 0) as follower_user_code"),
                DB::raw("h.$masterTradeAccountCol as master_trade_account_id"),
                DB::raw("h.$followerTradeAccountCol as follower_trade_account_id"),
                DB::raw('ta_master.trade_account_type as master_trade_account_type'),
                DB::raw('ta_follower.trade_account_type as follower_trade_account_type'),
                DB::raw("$investAmountExpr as invest_amount"),
                DB::raw('ml.level_pos as level_pos'),
                DB::raw("h.$createdAtCol as copy_start_time"),
                DB::raw("$endTimeExpr as copy_end_time"),
                DB::raw("h.$statusCol as follow_status"),
            ]);

        if (! empty($filters['master_user_code'])) {
            $query->where('ta_master.user_code', (int) $filters['master_user_code']);
        }
        if (! empty($filters['master_trade_account_id'])) {
            $query->where("h.$masterTradeAccountCol", (int) $filters['master_trade_account_id']);
        }
        if (($filters['master_trade_account_type'] ?? null) !== null && $filters['master_trade_account_type'] !== '') {
            $query->where('ta_master.trade_account_type', (int) $filters['master_trade_account_type']);
        }
        if (! empty($filters['follower_user_code'])) {
            $query->where('ta_follower.user_code', (int) $filters['follower_user_code']);
        }
        if (($filters['follow_status'] ?? null) !== null && $filters['follow_status'] !== '') {
            $query->where("h.$statusCol", (int) $filters['follow_status']);
        }
        if (($filters['master_trade_account_type'] ?? null) !== null && $filters['master_trade_account_type'] !== '') {
            $tradeAccountType = (int) $filters['master_trade_account_type'];
            $allowedTypes = array_map(
                static fn (TypeTradeAccount $type): int => $type->value,
                TypeTradeAccount::cases()
            );
            if (in_array($tradeAccountType, $allowedTypes, true)) {
                $query->where('ta_master.trade_account_type', $tradeAccountType);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        if (($filters['master_trade_account_id'] ?? null) !== null && $filters['master_trade_account_id'] !== '') {
            $query->where("h.$masterTradeAccountCol", (int) $filters['master_trade_account_id']);
        }
        if (! empty($filters['copy_start_time_start'])) {
            $query->where("h.$createdAtCol", '>=', (string) $filters['copy_start_time_start']);
        }
        if (! empty($filters['copy_start_time_end'])) {
            $query->where("h.$createdAtCol", '<=', (string) $filters['copy_start_time_end']);
        }
        if (! empty($filters['copy_end_time_start'])) {
            $query->where("h.$statusCol", 1)
                ->where("h.$opTimeCol", '>=', (string) $filters['copy_end_time_start']);
        }
        if (! empty($filters['copy_end_time_end'])) {
            $query->where("h.$statusCol", 1)
                ->where("h.$opTimeCol", '<=', (string) $filters['copy_end_time_end']);
        }
        if (($filters['has_commission'] ?? null) !== null && $filters['has_commission'] !== '') {
            $this->applyHasCommissionFilter($query, $statusCol, $followerTradeAccountCol, (int) $filters['has_commission']);
        }

        $total = (clone $query)->count();
        $query->orderByDesc("h.$createdAtCol")
            ->orderByDesc('h.id');

        if ($limit >= 0 && $offset >= 0) {
            $query->offset(max(0, $offset))->limit(max(1, $limit));
        }

        return [$query->get()->all(), $total];
    }

    private function resolveFirstExistingColumn(string $connection, string $table, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if (Schema::connection($connection)->hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * 按是否可产生分佣筛选跟随关系。
     *
     * 可产生分佣（1）= 跟随中且跟随者是直客（本人不是代理、无上级代理归属）；
     * 不可产生（0）= 已结束，或跟随者不是直客。
     *
     * @param  Builder  $query
     */
    private function applyHasCommissionFilter($query, string $statusCol, string $followerTradeAccountCol, int $hasCommission): void
    {
        if (! in_array($hasCommission, [0, 1], true)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $directCustomerExists = static function ($query) use ($followerTradeAccountCol) {
            $query->selectRaw(1)
                ->from(DB::raw(DB_API.'.trade_account as ta2'))
                ->join(DB::raw(DB_API.'.user as u2'), 'u2.user_code', '=', 'ta2.user_code')
                ->leftJoin(DB::raw(DB_API.'.ib_account as ib2'), 'ib2.user_code', '=', 'u2.user_code')
                ->whereColumn('ta2.trade_account_id', "h.$followerTradeAccountCol")
                ->where(static function ($query) {
                    $query->whereNull('u2.ib_affiliation')->orWhere('u2.ib_affiliation', 0);
                })
                ->whereNull('ib2.user_code');
        };

        if ($hasCommission === 1) {
            $query->where("h.$statusCol", Copy::STATUS_FOLLOWING)
                ->whereExists($directCustomerExists);

            return;
        }

        $query->where(static function ($query) use ($statusCol, $directCustomerExists) {
            $query->where("h.$statusCol", '!=', Copy::STATUS_FOLLOWING)
                ->orWhereNotExists($directCustomerExists);
        });
    }

    /**
     * @param array{
     *   master_level?: string|null,
     *   level_name?: string|null,
     *   status?: int|string|null,
     *   is_initial?: int|string|null,
     *   is_default_level?: int|string|null
     * } $filters
     * @return array{0: Collection<int, object>, 1: int, 2: int}
     */
    public function getMasterLevels(array $filters, int $limit, int $offset, ?int $levelPos): array
    {
        $query = MasterLevel::query()->from('master_level as ml')->select(['ml.*']);

        if ($levelPos !== null) {
            $query->where('ml.level_pos', $levelPos);
        }
        if (! empty($filters['level_name'])) {
            $this->appendJsonNameKeywordFilter($query, (string) $filters['level_name'], 'ml.name');
        }
        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('ml.status', (int) $filters['status']);
        }
        if (($filters['is_initial'] ?? null) !== null && $filters['is_initial'] !== '') {
            $isInitial = (int) $filters['is_initial'];
            if (in_array($isInitial, [ApiMasterLevel::IS_INITIAL_NO, ApiMasterLevel::IS_INITIAL_YES], true)) {
                $query->where('ml.is_initial', $isInitial);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        if (($filters['is_default_level'] ?? null) !== null && $filters['is_default_level'] !== '') {
            $isDefaultLevel = (int) $filters['is_default_level'];
            if ($isDefaultLevel === ApiMasterLevel::IS_INITIAL_YES) {
                $query->where('ml.level_pos', ApiMasterLevel::DEFAULT_LEVEL_POS);
            } elseif ($isDefaultLevel === ApiMasterLevel::IS_INITIAL_NO) {
                $query->where('ml.level_pos', '!=', ApiMasterLevel::DEFAULT_LEVEL_POS);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $summaryQuery = clone $query;
        $total = (clone $summaryQuery)->count('ml.id');
        $nextLevelId = ((int) ((clone $summaryQuery)->max('ml.level_pos') ?? 0)) + 1;

        if ($limit >= 0 && $offset >= 0) {
            $query->offset($offset)->limit($limit);
        }

        $rows = $query->orderBy('ml.level_pos')->get();

        return [$rows, $total, $nextLevelId];
    }

    /**
     * @return Collection<int, MasterLevel>
     */
    public function getAllMasterLevels(): Collection
    {
        return MasterLevel::query()
            ->orderBy('level_pos')
            ->get();
    }

    /**
     * @return array<int, array{level_pos:int,name:array|string|null}>
     */
    public function getEnabledMasterLevelParams(): array
    {
        return ApiMasterLevel::query()
            ->where('status', ApiMasterLevel::STATUS_ENABLED)
            ->orderBy('level_pos')
            ->orderBy('id')
            ->get(['level_pos', 'name'])
            ->toArray();
    }

    public function findMasterLevelById(int $levelId): ?MasterLevel
    {
        return MasterLevel::query()->where('id', $levelId)->first();
    }

    public function findInitialMasterLevel(?int $ignoreId = null): ?MasterLevel
    {
        $query = MasterLevel::query()
            ->where('is_initial', ApiMasterLevel::IS_INITIAL_YES)
            ->orderBy('level_pos')
            ->orderBy('id')
            ->lockForUpdate();

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->first();
    }

    public function findEnabledInitialMasterLevelId(): ?int
    {
        $levelId = MasterLevel::query()
            ->where('status', ApiMasterLevel::STATUS_ENABLED)
            ->where('is_initial', ApiMasterLevel::IS_INITIAL_YES)
            ->orderBy('level_pos')
            ->orderBy('id')
            ->value('id');

        return $levelId === null ? null : (int) $levelId;
    }

    public function existsMasterAccountByLevelId(int $levelId): bool
    {
        return MasterAccount::query()
            ->where('level_id', $levelId)
            ->exists();
    }

    public function deleteMasterLevel(int $levelId): int
    {
        return MasterLevel::query()->where('id', $levelId)->delete();
    }

    public function decrementMasterLevelPosGreaterThan(int $levelPos): int
    {
        return MasterLevel::query()
            ->where('level_pos', '>', $levelPos)
            ->decrement('level_pos');
    }

    public function getMaxMasterLevelPos(): int
    {
        return (int) (MasterLevel::query()->max('level_pos') ?? 0);
    }

    public function createMasterLevel(array $payload): MasterLevel
    {
        return MasterLevel::query()->create($payload);
    }

    public function updateMasterLevelById(int $levelId, array $payload): int
    {
        return MasterLevel::query()->where('id', $levelId)->update($payload);
    }

    public function existsMasterAccountByUserCode(int $userCode): bool
    {
        return MasterAccount::query()
            ->where('user_code', $userCode)
            ->exists();
    }

    public function updateMasterPrivateByUserCode(int $userCode, int $isPrivate): int
    {
        return MasterAccount::query()
            ->where('user_code', $userCode)
            ->update(['is_private' => $isPrivate]);
    }

    public function existsMasterAccountByTradeAccountId(int $tradeAccountId): bool
    {
        return MasterAccount::query()
            ->where('trade_account_id', $tradeAccountId)
            ->exists();
    }

    /**
     * @param  array<int, int>  $tradeAccountIds
     * @return Collection<int, MasterAccount>
     */
    public function getMasterAccountsByTradeAccountIds(array $tradeAccountIds, bool $lockForUpdate = false): Collection
    {
        if ($tradeAccountIds === []) {
            return new Collection;
        }

        $query = MasterAccount::query()
            ->select(['trade_account_id', 'status'])
            ->whereIn('trade_account_id', $tradeAccountIds);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    public function updateMasterPrivateByTradeAccountId(int $tradeAccountId, int $isPrivate): int
    {
        return MasterAccount::query()
            ->where('trade_account_id', $tradeAccountId)
            ->update(['is_private' => $isPrivate]);
    }

    public function updateMasterLevelIdByTradeAccountId(int $tradeAccountId, int $levelId): int
    {
        return MasterAccount::query()
            ->where('trade_account_id', $tradeAccountId)
            ->update(['level_id' => $levelId]);
    }

    public function updateMasterProtectionTimeByTradeAccountId(int $tradeAccountId, array $payload): int
    {
        return MasterAccount::query()
            ->where('trade_account_id', $tradeAccountId)
            ->update($payload);
    }

    /**
     * @param  array<int, int>  $tradeAccountIds
     */
    public function updateMasterProtectionTimeByTradeAccountIds(array $tradeAccountIds, array $payload): int
    {
        if ($tradeAccountIds === []) {
            return 0;
        }

        return MasterAccount::query()
            ->whereIn('trade_account_id', $tradeAccountIds)
            ->update($payload);
    }

    /**
     * @param  array<string, string>  $localizedNames
     */
    public function existsByLocalizedNames(array $localizedNames, ?int $ignoreId = null): bool
    {
        if (empty($localizedNames)) {
            return false;
        }

        $query = MasterLevel::query();
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        $query->where(function (Builder $builder) use ($localizedNames): void {
            foreach ($localizedNames as $lang => $name) {
                $builder->orWhere("name->$lang", $name);
            }
        });

        return $query->exists();
    }

    public function findPrevMasterLevel(int $levelPos): ?MasterLevel
    {
        return MasterLevel::query()
            ->where('level_pos', '<', $levelPos)
            ->orderByDesc('level_pos')
            ->first();
    }

    public function findNextMasterLevel(int $levelPos): ?MasterLevel
    {
        return MasterLevel::query()
            ->where('level_pos', '>', $levelPos)
            ->orderBy('level_pos')
            ->first();
    }

    private function appendJsonNameKeywordFilter(Builder $query, string $keyword, string $column): void
    {
        $like = $this->like($keyword);
        $langs = $this->getLangKeys();

        $query->where(function (Builder $builder) use ($column, $langs, $like): void {
            foreach ($langs as $lang) {
                $builder->orWhere("$column->$lang", 'like', $like);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function getLangKeys(): array
    {
        return array_keys(config('lang.languages', []));
    }

    private function like(string $value): string
    {
        $escaped = addcslashes($value, '%_');

        return sprintf('%%%s%%', $escaped);
    }

    /**
     * @param array{
     *   user_code?: int|string|null,
     *   status?: int|string|null,
     *   reviewed_by_ids?: array<int, int>|null,
     *   applied_at_start?: string|null,
     *   applied_at_end?: string|null
     * } $filters
     * @return array{0: Collection<int, MasterApplication>, 1: int}
     */
    public function getMasterApplications(array $filters, int $limit, int $offset): array
    {
        $query = MasterApplication::query()
            ->from('master_application')
            ->select([
                'id',
                'user_code',
                'project',
                'display_name',
                'bio',
                'project_type',
                'trade_platform',
                'profit_share_rate',
                'initial_funding',
                'status',
                'rejection_reason',
                'applied_at',
                'reviewed_by',
            ]);

        if (($filters['user_code'] ?? null) !== null && $filters['user_code'] !== '') {
            $query->where('user_code', (int) $filters['user_code']);
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (array_key_exists('reviewed_by_ids', $filters) && $filters['reviewed_by_ids'] !== null) {
            $reviewedByIds = $filters['reviewed_by_ids'] ?? [];
            if (empty($reviewedByIds)) {
                return [new Collection, 0];
            }

            $query->whereIn('reviewed_by', $reviewedByIds);
        }

        if (($filters['applied_at_start'] ?? '') !== '') {
            $query->where('applied_at', '>=', (string) $filters['applied_at_start']);
        }

        if (($filters['applied_at_end'] ?? '') !== '') {
            $query->where('applied_at', '<=', (string) $filters['applied_at_end']);
        }

        $total = (clone $query)->count('id');

        if ($limit >= 0 && $offset >= 0) {
            $query->offset(max(0, $offset))->limit(max(1, $limit));
        }

        return [$query->orderByDesc('id')->get(), $total];
    }

    public function findMasterApplicationForUpdate(int $id): ?MasterApplication
    {
        return MasterApplication::query()
            ->where('id', $id)
            ->lockForUpdate()
            ->first();
    }

    public function saveMasterApplication(MasterApplication $application): void
    {
        $application->save();
    }

    /**
     * @param array{
     *   master_user_code?: int|string|null,
     *   master_level?: string|null,
     *   status?: int|string|null,
     *   is_manual?: int|string|null,
     *   is_private?: int|string|null,
     *   trade_account_type?: int|string|null
     * } $filters
     * @return array{0: Collection<int, object>, 1: int}
     */
    public function getMasters(array $filters, int $limit, int $offset, ?int $levelPos): array
    {
        $query = MasterAccount::query()
            ->from('master_account as ma')
            ->join(DB::raw(DB_API.'.trade_account as ta'), function ($join) {
                $join->on('ta.trade_account_id', '=', 'ma.trade_account_id')
                    ->on('ta.user_code', '=', 'ma.user_code');
            })
            ->leftJoin((new MasterLevel)->getTable().' as ml', 'ml.id', '=', 'ma.level_id')
            ->leftJoin(DB::raw(DB_TS.'.account as a'), 'a.login', '=', 'ma.trade_account_id')
            ->leftJoinSub(
                Order::query()
                    ->where('cmd', '<', 2)
                    ->where('close_time', '>=', now()->subDays(29)->toDateString())
                    ->selectRaw('login as trade_account_id, COALESCE(SUM(`profit` + `swaps` + `close_fee` + `open_fee` + `commission`), 0) as profit_amount_30d, COALESCE(SUM(`margin_origin`), 0) as total_margin_30d, COUNT(*) as order_count_30d')
                    ->groupBy('trade_account_id'),
                'mtd30',
                'mtd30.trade_account_id',
                '=',
                'ma.trade_account_id'
            )
            ->leftJoinSub(
                DB::connection(DB_API)
                    ->table('master_status_change_log')
                    ->select('user_code', 'operator_id')
                    ->whereIn('id', function ($q) {
                        $q->selectRaw('MAX(id)')
                            ->from('master_status_change_log')
                            ->where('after_status', '=', MasterAccount::STATUS_SUCCESS)
                            ->groupBy('user_code');
                    }),
                'mscl',
                'mscl.user_code',
                '=',
                'ma.user_code'
            )
            ->leftJoinSub(
                DB::connection(DB_API)
                    ->table('master_status_change_log')
                    ->selectRaw('user_code, remark')
                    ->whereIn('id', function ($q) {
                        $q->selectRaw('MAX(id)')
                            ->from('master_status_change_log')
                            ->groupBy('user_code');
                    }),
                'latest_mscl',
                'latest_mscl.user_code',
                '=',
                'ma.user_code'
            )
            ->select([
                'ma.id',
                'ma.trade_account_id',
                'ta.trade_account_type',
                'ma.user_code',
                'ma.total_margin',
                'ma.follower_fund',
                'ma.follower_count',
                'ma.commission_amount',
                'ma.status',
                'ma.is_private',
                'ma.status_updated_at',
                'ma.level_protection_started_at',
                'ma.level_protection_ended_at',
                'ml.level_pos',
                'ml.commission_rate',
                'ml.fee_commission_rate',
                'mtd30.profit_amount_30d',
                'mtd30.total_margin_30d',
                'mtd30.order_count_30d',
                'a.balance',
                'mscl.operator_id',
                'latest_mscl.remark as reason',
            ]);

        $this->applyMasterDisplayTradeAccountTypeFilter($query, 'ta.trade_account_type', $filters['trade_account_type'] ?? null);

        if (! empty($filters['master_user_code'])) {
            $query->where('ma.user_code', (int) $filters['master_user_code']);
        }

        if (! empty($filters['trade_account_id'])) {
            $query->where('ma.trade_account_id', (int) $filters['trade_account_id']);
        }

        if ($levelPos !== null) {
            $query->where('ml.level_pos', $levelPos);
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('ma.status', (int) $filters['status']);
        }

        if (($filters['is_manual'] ?? null) !== null && $filters['is_manual'] !== '') {
            $isManual = (int) $filters['is_manual'];
            if ($isManual === MasterAccount::IS_MANUAL_MANUAL) {
                $query->whereNotNull('mscl.operator_id');
            } elseif ($isManual === MasterAccount::IS_MANUAL_AUTO) {
                $query->whereNull('mscl.operator_id');
            }
        }

        if (($filters['is_private'] ?? null) !== null && $filters['is_private'] !== '') {
            $query->where('ma.is_private', (int) $filters['is_private']);
        }

        $total = (clone $query)->count('ma.id');

        if ($limit >= 0 && $offset >= 0) {
            $query->offset($offset)->limit($limit);
        }

        $rows = $query
            ->orderByRaw('(SELECT MAX(ma2.id) FROM '.(new MasterAccount)->getTable().' AS ma2 WHERE ma2.user_code = ma.user_code) DESC')
            ->orderBy('ma.user_code')
            ->get();

        return [$rows, $total];
    }

    /**
     * 汇总高手已获得（已结算）的手续费分佣金额。
     *
     * @param  array<int, int>  $masterTradeAccountIds
     * @return array<int, string> trade_account_id => 已获得手续费分佣金额
     */
    public function getSettledFeeCommissionByMasterTradeAccountIds(array $masterTradeAccountIds): array
    {
        if ($masterTradeAccountIds === []) {
            return [];
        }

        return MasterCommissionRecord::query()
            ->whereIn('master_trade_account_id', $masterTradeAccountIds)
            ->where('status', MasterCommissionRecord::STATUS_SETTLED)
            ->selectRaw('master_trade_account_id, COALESCE(SUM(fee_commission_amount), 0) as fee_commission_amount')
            ->groupBy('master_trade_account_id')
            ->pluck('fee_commission_amount', 'master_trade_account_id')
            ->toArray();
    }

    /**
     * @return Collection<int, MasterAccount>
     */
    public function getMasterAccountsForDelist(int $masterUserCode): Collection
    {
        return MasterAccount::query()
            ->where('user_code', $masterUserCode)
            ->lockForUpdate()
            ->get();
    }

    /**
     * 通过交易账号 ID 获取高手账号记录
     *
     * @param  int  $tradeAccountId  交易账号ID
     * @return Collection<int, MasterAccount>
     */
    public function getMasterAccountsByTradeAccountId(int $tradeAccountId): Collection
    {
        return MasterAccount::query()
            ->where('trade_account_id', $tradeAccountId)
            ->lockForUpdate()
            ->get();
    }

    public function saveMasterAccount(MasterAccount $account): void
    {
        $account->save();
    }

    /**
     * @param  array<int, array<string, mixed>>  $logs
     */
    public function batchInsertStatusLogs(array $logs): void
    {
        if (empty($logs)) {
            return;
        }

        MasterStatusChangeLog::query()->insert($logs);
    }

    /**
     * @param array{
     *   order_id?: string|null,
     *   master_user_code?: int|string|null,
     *   follower_user_code?: int|string|null,
     *   master_level?: string|null,
     *   master_trade_account_id?: int|string|null,
     *   master_trade_account_type?: int|string|null,
     *   follower_trade_account_id?: int|string|null,
     *   profit_time_start?: string|null,
     *   profit_time_end?: string|null,
     *   status?: int|string|null
     * } $filters
     * @return array{0: Collection<int, object>, 1: int}
     */
    public function getCommissionRecords(
        array $filters,
        int $limit,
        int $offset,
        ?int $levelPos
    ): array {
        $query = MasterCommissionRecord::query()->from('master_commission_record as mcr')->select(['mcr.*']);

        // 账号类型必须绑定分润记录上的实际交易账号，避免同一用户的其他账号串入。
        $query->addSelect([
            DB::raw('(SELECT ta.trade_account_type FROM '.DB_API.'.trade_account ta WHERE ta.trade_account_id = mcr.master_trade_account_id LIMIT 1) as master_trade_account_type'),
            DB::raw('(SELECT ta.trade_account_type FROM '.DB_API.'.trade_account ta WHERE ta.trade_account_id = mcr.follower_trade_account_id LIMIT 1) as follower_trade_account_type'),
        ]);

        if (! empty($filters['order_id'])) {
            $query->where('mcr.follower_ticket', (string) $filters['order_id']);
        }
        if (! empty($filters['master_user_code'])) {
            $query->where('mcr.master_user_code', (int) $filters['master_user_code']);
        }
        if (! empty($filters['follower_user_code'])) {
            $query->where('mcr.follower_user_code', (int) $filters['follower_user_code']);
        }
        if ($levelPos !== null) {
            $query->where('mcr.level_pos', $levelPos);
        }
        $this->applyCommissionAccountFilters($query, $filters);
        if (! empty($filters['close_time_start'])) {
            $query->where('mcr.close_time', '>=', (string) $filters['close_time_start']);
        }
        if (! empty($filters['close_time_end'])) {
            $query->where('mcr.close_time', '<=', (string) $filters['close_time_end']);
        }
        if (! empty($filters['profit_time_start'])) {
            $query->where('mcr.settled_at', '>=', (string) $filters['profit_time_start']);
        }
        if (! empty($filters['profit_time_end'])) {
            $query->where('mcr.settled_at', '<=', (string) $filters['profit_time_end']);
        }
        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('mcr.status', (int) $filters['status']);
        }

        $total = (clone $query)->count('mcr.id');

        if ($limit >= 0 && $offset >= 0) {
            $query->offset($offset)->limit($limit);
        }

        $rows = $query->orderByDesc('mcr.id')->get();

        return [$rows, $total];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyCommissionAccountFilters(Builder $query, array $filters): void
    {
        if (($filters['master_trade_account_id'] ?? null) !== null && $filters['master_trade_account_id'] !== '') {
            $query->where('mcr.master_trade_account_id', (int) $filters['master_trade_account_id']);
        }
        if (($filters['master_trade_account_type'] ?? null) !== null && $filters['master_trade_account_type'] !== '') {
            $tradeAccountType = (int) $filters['master_trade_account_type'];
            $query->whereRaw('EXISTS (SELECT 1 FROM '.DB_API.'.trade_account ta WHERE ta.trade_account_id = mcr.master_trade_account_id AND ta.trade_account_type = ?)', [$tradeAccountType]);
        }
        if (($filters['follower_trade_account_id'] ?? null) !== null && $filters['follower_trade_account_id'] !== '') {
            $query->where('mcr.follower_trade_account_id', (int) $filters['follower_trade_account_id']);
        }
    }

    /**
     * 潜在高手列表：用户表中不在 master_account 且已开通 UTA/赠金交易账户的用户。
     * 以交易账号维度展示，每个交易账号独立一行。
     *
     * @param array{
     *   user_code?: int|string|null,
     *   trade_account_id?: int|string|null,
     *   min_balance?: string|null,
     *   min_order_count_30d?: int|string|null,
     *   min_profit_rate_30d?: string|null,
     *   trade_account_type?: int|string|null
     * } $filters
     * @return array{0: Collection<int, object>, 1: int}
     */
    public function getPotentialMasters(array $filters, int $limit, int $offset): array
    {
        $subQuery30d = Order::query()
            ->where('cmd', '<', 2)
            ->where('close_time', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('login as trade_account_id,
                COALESCE(SUM(`profit` + `swaps` + `close_fee` + `open_fee` + `commission`), 0) as profit_amount_30d,
                COALESCE(SUM(`margin_origin`), 0) as total_margin_30d,
                COUNT(*) as order_count_30d')
            ->groupBy('trade_account_id');

        $query = DB::connection(DB_API)
            ->table('user as u')
            ->select([
                'u.user_code',
                'u.type',
                'ta.trade_account_id',
                'ta.trade_account_type',
                DB::raw('COALESCE(SUM(a.balance), 0) as balance'),
                DB::raw('COALESCE(SUM(mtd30.order_count_30d), 0) as order_count_30d'),
                DB::raw('COALESCE(SUM(mtd30.profit_amount_30d), 0) as profit_amount_30d'),
                DB::raw('COALESCE(SUM(mtd30.total_margin_30d), 0) as total_margin_30d'),
            ])
            ->join('trade_account as ta', function ($join) {
                $join->on('ta.user_code', '=', 'u.user_code')
                    ->where('ta.status', '=', 1)
                    ->whereNotNull('ta.trade_account_id');
            })
            ->leftJoin(DB::raw(DB_TS.'.account as a'), 'a.login', '=', 'ta.trade_account_id')
            ->leftJoinSub($subQuery30d, 'mtd30', 'mtd30.trade_account_id', '=', 'ta.trade_account_id')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('master_account')
                    ->whereColumn('master_account.trade_account_id', 'ta.trade_account_id');
            })
            ->where('u.type', '!=', 2) // 排除黑名单
            ->groupBy('ta.trade_account_id', 'u.user_code', 'u.type', 'ta.trade_account_type');

        $this->applyMasterDisplayTradeAccountTypeFilter($query, 'ta.trade_account_type', $filters['trade_account_type'] ?? null);

        if (! empty($filters['user_code'])) {
            $query->where('u.user_code', (int) $filters['user_code']);
        }

        if (! empty($filters['trade_account_id'])) {
            $query->where('ta.trade_account_id', (int) $filters['trade_account_id']);
        }

        $havingClauses = [];
        if (($filters['min_balance'] ?? null) !== null && $filters['min_balance'] !== '') {
            $havingClauses[] = 'balance >= '.(float) $filters['min_balance'];
        }
        if (($filters['min_order_count_30d'] ?? null) !== null && $filters['min_order_count_30d'] !== '') {
            $havingClauses[] = 'order_count_30d >= '.(int) $filters['min_order_count_30d'];
        }
        if (($filters['min_profit_rate_30d'] ?? null) !== null && $filters['min_profit_rate_30d'] !== '') {
            $rate = (float) $filters['min_profit_rate_30d'] / 100;
            $havingClauses[] = 'CASE WHEN total_margin_30d > 0 THEN profit_amount_30d / total_margin_30d ELSE 0 END >= '.$rate;
        }

        foreach ($havingClauses as $clause) {
            $query->havingRaw($clause);
        }

        $baseQuery = clone $query;
        $total = DB::connection(DB_API)
            ->table(DB::raw("({$baseQuery->toSql()}) as sub"))
            ->mergeBindings($baseQuery)
            ->count();

        if ($limit >= 0 && $offset >= 0) {
            $query->offset($offset)->limit($limit);
        }

        $rows = $query->orderBy('u.user_code')->get();

        return [$rows, $total];
    }
}
