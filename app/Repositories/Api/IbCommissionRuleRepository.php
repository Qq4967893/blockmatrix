<?php

namespace App\Repositories\Api;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Models\Api\IbCommissionRule;
use Scaffold\Framework\Repositories\Repository;

class IbCommissionRuleRepository extends Repository
{
    public const STATUS_DRAFT = 0;

    public const STATUS_PENDING_REVIEW = 1;

    public const STATUS_APPROVED = 2;

    public const STATUS_REJECTED = 3;

    public const STATUS_DISABLED = 4;

    public const REVIEW_ACTION_APPROVE = 1;

    public const REVIEW_ACTION_REJECT = 2;

    public function __construct(IbCommissionRule $model)
    {
        parent::__construct($model);
    }

    /**
     * @param array{
     *     name?: string|null,
     *     level_name?: string|null,
     *     is_effective?: int|null,
     *     review_status?: int|null
     * } $filters
     * @return array{0: array<int, array>, 1: int}
     */
    public function getList(array $filters, int $limit, int $offset): array
    {
        $query = DB::connection(DB_API)
            ->table('ib_commission_rule')
            ->leftJoin('ib_commission_rule_version as latest_version', function ($join): void {
                $join->on('latest_version.rule_id', '=', 'ib_commission_rule.id')
                    ->whereRaw(
                        'latest_version.id = (
                            SELECT v.id
                            FROM ib_commission_rule_version v
                            WHERE v.rule_id = ib_commission_rule.id
                            ORDER BY v.version_no DESC, v.id DESC
                            LIMIT 1
                        )'
                    );
            })
            ->select([
                'ib_commission_rule.id',
                'ib_commission_rule.name',
                'ib_commission_rule.current_version_id',
                'latest_version.base_commission_rate',
                'latest_version.promotion_commission_rate',
                'latest_version.created_at',
                'latest_version.reviewed_at',
                'latest_version.status as version_status',
                'latest_version.rejection_reason',
            ]);

        if (($filters['name'] ?? '') !== '') {
            $this->appendJsonNameKeywordFilter($query, (string) $filters['name'], 'ib_commission_rule.name');
        }

        if (($filters['level_name'] ?? '') !== '') {
            $keyword = (string) $filters['level_name'];
            $query->whereExists(function (Builder $subQuery) use ($keyword): void {
                $subQuery->selectRaw('1')
                    ->from('ib_level')
                    ->whereColumn('ib_level.commission_rule_id', 'ib_commission_rule.id')
                    ->where('ib_level.status', 1);

                $this->appendJsonNameKeywordFilter($subQuery, $keyword, 'ib_level.name');
            });
        }

        if (($filters['is_effective'] ?? null) !== null) {
            $isEffective = (int) $filters['is_effective'];
            $query->where(function (Builder $builder) use ($isEffective): void {
                $existsQuery = function (Builder $subQuery): void {
                    $subQuery->selectRaw('1')
                        ->from('ib_level')
                        ->whereColumn('ib_level.commission_rule_id', 'ib_commission_rule.id')
                        ->where('ib_level.status', 1);
                };

                if ($isEffective === 1) {
                    $builder->whereExists($existsQuery);
                } else {
                    $builder->whereNotExists($existsQuery);
                }
            });
        }

        if (($filters['review_status'] ?? null) !== null) {
            $statuses = $this->mapReviewStatusToDbStatus((int) $filters['review_status']);
            if (empty($statuses)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('latest_version.status', $statuses);
            }
        }

        $total = (clone $query)->count('ib_commission_rule.id');
        $data = $query
            ->orderBy('latest_version.base_commission_rate')
            ->orderByDesc('ib_commission_rule.id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        $rows = array_map(static fn ($row): array => (array) $row, $data);

        return [$rows, $total];
    }

    public function findById(int $id): ?object
    {
        return DB::connection(DB_API)
            ->table('ib_commission_rule')
            ->where('id', $id)
            ->first();
    }

    public function createRule(array $payload): object
    {
        $id = DB::connection(DB_API)
            ->table('ib_commission_rule')
            ->insertGetId($this->normalizeRuleWritePayload($payload));

        return $this->findById((int) $id);
    }

    public function updateRule(int $id, array $payload): void
    {
        DB::connection(DB_API)
            ->table('ib_commission_rule')
            ->where('id', $id)
            ->update($this->normalizeRuleWritePayload($payload));
    }

    public function findVersionById(int $versionId): ?object
    {
        return DB::connection(DB_API)
            ->table('ib_commission_rule_version')
            ->where('id', $versionId)
            ->first();
    }

    public function findLatestVersionByRuleId(int $ruleId): ?object
    {
        return DB::connection(DB_API)
            ->table('ib_commission_rule_version')
            ->where('rule_id', $ruleId)
            ->orderByDesc('version_no')
            ->orderByDesc('id')
            ->first();
    }

    public function createVersion(array $payload): object
    {
        $id = DB::connection(DB_API)->table('ib_commission_rule_version')->insertGetId($payload);

        return $this->findVersionById((int) $id);
    }

    public function updateVersion(int $versionId, array $payload): void
    {
        DB::connection(DB_API)
            ->table('ib_commission_rule_version')
            ->where('id', $versionId)
            ->update($payload);
    }

    public function markOtherApprovedVersionsDisabled(int $ruleId, int $approvedVersionId): void
    {
        DB::connection(DB_API)
            ->table('ib_commission_rule_version')
            ->where('rule_id', $ruleId)
            ->where('id', '!=', $approvedVersionId)
            ->where('status', self::STATUS_APPROVED)
            ->update([
                'status' => self::STATUS_DISABLED,
            ]);
    }

    public function existsPendingVersion(int $ruleId): bool
    {
        return DB::connection(DB_API)
            ->table('ib_commission_rule_version')
            ->where('rule_id', $ruleId)
            ->where('status', self::STATUS_PENDING_REVIEW)
            ->exists();
    }

    public function getCurrentVersionStatus(int $ruleId): ?int
    {
        $row = DB::connection(DB_API)
            ->table('ib_commission_rule')
            ->leftJoin('ib_commission_rule_version as current_version', 'current_version.id', '=', 'ib_commission_rule.current_version_id')
            ->where('ib_commission_rule.id', $ruleId)
            ->select('current_version.status')
            ->first();

        return $row?->status === null ? null : (int) $row->status;
    }

    public function getEffectiveBaseCommissionRate(int $ruleId): ?string
    {
        $row = DB::connection(DB_API)
            ->table('ib_commission_rule as icr')
            ->join('ib_commission_rule_version as icrv', 'icrv.id', '=', 'icr.current_version_id')
            ->where('icr.id', $ruleId)
            ->where('icrv.status', self::STATUS_APPROVED)
            ->first(['icrv.base_commission_rate']);

        return $row?->base_commission_rate === null ? null : (string) $row->base_commission_rate;
    }

    /**
     * Find another rule whose currently effective base commission rate is the same.
     */
    public function findEffectiveBaseCommissionRateConflict(string $baseCommissionRate, ?int $ignoreRuleId = null): ?object
    {
        return DB::connection(DB_API)
            ->table('ib_commission_rule as icr')
            ->join('ib_commission_rule_version as icrv', 'icrv.id', '=', 'icr.current_version_id')
            ->where('icrv.status', self::STATUS_APPROVED)
            ->where('icrv.base_commission_rate', $baseCommissionRate)
            ->when($ignoreRuleId !== null, static function (Builder $query) use ($ignoreRuleId): void {
                $query->where('icr.id', '!=', $ignoreRuleId);
            })
            ->orderBy('icr.id')
            ->first(['icr.id', 'icr.name', 'icrv.base_commission_rate']);
    }

    /**
     * Find another rule whose latest submitted base commission rate is the same.
     */
    public function findPendingBaseCommissionRateConflict(string $baseCommissionRate, ?int $ignoreRuleId = null): ?object
    {
        return DB::connection(DB_API)
            ->table('ib_commission_rule as icr')
            ->leftJoin('ib_commission_rule_version as latest_version', static function ($join): void {
                $join->on('latest_version.rule_id', '=', 'icr.id')
                    ->whereRaw(
                        'latest_version.id = (
                            SELECT v.id
                            FROM ib_commission_rule_version v
                            WHERE v.rule_id = icr.id
                            ORDER BY v.version_no DESC, v.id DESC
                            LIMIT 1
                        )'
                    );
            })
            ->whereNotNull('latest_version.id')
            ->whereIn('latest_version.status', [self::STATUS_DRAFT, self::STATUS_PENDING_REVIEW])
            ->where('latest_version.base_commission_rate', $baseCommissionRate)
            ->when($ignoreRuleId !== null, static function (Builder $query) use ($ignoreRuleId): void {
                $query->where('icr.id', '!=', $ignoreRuleId);
            })
            ->orderBy('icr.id')
            ->first(['icr.id', 'icr.name', 'latest_version.base_commission_rate']);
    }

    public function lockEffectiveRules(): void
    {
        DB::connection(DB_API)
            ->table('ib_commission_rule as icr')
            ->leftJoin('ib_commission_rule_version as icrv', 'icrv.id', '=', 'icr.current_version_id')
            ->orderBy('icr.id')
            ->lockForUpdate()
            ->get(['icr.id']);
    }

    public function existsActiveLinkedLevel(int $ruleId): bool
    {
        return DB::connection(DB_API)
            ->table('ib_level')
            ->where('commission_rule_id', $ruleId)
            ->where('status', 1)
            ->exists();
    }

    /**
     * @param  array<int>  $ruleIds
     * @return array<int, array<int, string>>
     */
    public function getLinkedLevelNamesByRuleIds(array $ruleIds, string $lang): array
    {
        if (empty($ruleIds)) {
            return [];
        }

        $rows = DB::connection(DB_API)
            ->table('ib_level')
            ->where('status', 1)
            ->whereIn('commission_rule_id', $ruleIds)
            ->get(['commission_rule_id as rule_row_id', 'name']);

        $result = [];
        foreach ($rows as $row) {
            $ruleId = (int) $row->rule_row_id;
            $result[$ruleId][] = $this->resolveLocalizedName($row->name, $lang);
        }

        return $result;
    }

    /**
     * @param  array<int>  $ruleIds
     * @return array<int, int>
     */
    public function getLinkedLevelCounts(array $ruleIds): array
    {
        if (empty($ruleIds)) {
            return [];
        }

        return DB::connection(DB_API)
            ->table('ib_level')
            ->selectRaw('commission_rule_id as rule_row_id, COUNT(*) as aggregate')
            ->where('status', 1)
            ->whereIn('commission_rule_id', $ruleIds)
            ->groupBy('commission_rule_id')
            ->pluck('aggregate', 'rule_row_id')
            ->map(static fn ($count): int => (int) $count)
            ->toArray();
    }

    public function existsLinkedLevels(int $ruleId): bool
    {
        return DB::connection(DB_API)
            ->table('ib_level')
            ->where('commission_rule_id', $ruleId)
            ->exists();
    }

    /**
     * @return array<int, array{id:int,name:array|string|null}>
     */
    public function getEffectiveOptions(): array
    {
        $rows = DB::connection(DB_API)
            ->table('ib_commission_rule')
            ->leftJoin('ib_commission_rule_version as current_version', 'current_version.id', '=', 'ib_commission_rule.current_version_id')
            ->leftJoin('ib_level', 'ib_level.commission_rule_id', '=', 'ib_commission_rule.id')
            ->where('current_version.status', self::STATUS_APPROVED)
            ->whereNull('ib_level.commission_rule_id')
            ->orderBy('ib_commission_rule.id')
            ->get(['ib_commission_rule.id', 'ib_commission_rule.name']);

        return $rows->map(static fn ($row): array => [
            'id' => (int) $row->id,
            'name' => $row->name,
        ])->all();
    }

    /**
     * Check whether any localized rule name conflicts with an existing rule.
     *
     * @param  array<string, string>  $localeMap
     * @return array{rule_id:int, conflicts: array<int, array{lang:string,name:string}>}|null
     */
    public function findLocalizedNameConflict(array $localeMap, ?int $ignoreRuleId = null): ?array
    {
        $incomingNames = $this->normalizeLocalizedNameMap($localeMap);
        if (empty($incomingNames)) {
            return null;
        }

        $query = DB::connection(DB_API)
            ->table('ib_commission_rule')
            ->select(['id', 'name']);

        if ($ignoreRuleId !== null) {
            $query->where('id', '!=', $ignoreRuleId);
        }

        foreach ($query->get() as $row) {
            $existingNames = $this->normalizeLocalizedNameMap($row->name ?? null);
            if (empty($existingNames)) {
                continue;
            }

            $conflicts = [];
            foreach ($incomingNames as $lang => $incomingName) {
                if ($incomingName === '') {
                    continue;
                }

                if (in_array($incomingName, $existingNames, true)) {
                    $conflicts[] = [
                        'lang' => $lang,
                        'name' => $this->originalLocalizedName($localeMap[$lang] ?? ''),
                    ];
                }
            }

            if (! empty($conflicts)) {
                return [
                    'rule_id' => (int) $row->id,
                    'conflicts' => $conflicts,
                ];
            }
        }

        return null;
    }

    public function deleteRuleWithVersions(int $ruleId): void
    {
        $versionIds = DB::connection(DB_API)
            ->table('ib_commission_rule_version')
            ->where('rule_id', $ruleId)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if (! empty($versionIds)) {
            DB::connection(DB_API)
                ->table('ib_commission_rule_detail')
                ->whereIn('rule_version_id', $versionIds)
                ->delete();
        }

        DB::connection(DB_API)
            ->table('ib_commission_rule_version')
            ->where('rule_id', $ruleId)
            ->delete();

        DB::connection(DB_API)
            ->table('ib_commission_rule')
            ->where('id', $ruleId)
            ->delete();
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
     * @return array<int>
     */
    private function mapReviewStatusToDbStatus(int $reviewStatus): array
    {
        return match ($reviewStatus) {
            0 => [self::STATUS_DRAFT],
            1 => [self::STATUS_PENDING_REVIEW],
            2 => [self::STATUS_APPROVED],
            3 => [self::STATUS_REJECTED],
            4 => [self::STATUS_DISABLED],
            default => [],
        };
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

    private function normalizeRuleWritePayload(array $payload): array
    {
        if (array_key_exists('name', $payload) && is_array($payload['name'])) {
            $payload['name'] = json_encode($payload['name'], JSON_UNESCAPED_UNICODE);
        }

        return $payload;
    }

    /**
     * @param  array<string, string>|string|null  $value
     * @return array<string, string>
     */
    private function normalizeLocalizedNameMap(array|string|null $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $names = [];
        foreach ($value as $lang => $name) {
            if (! is_string($lang) || ! is_scalar($name)) {
                continue;
            }

            $normalized = $this->normalizeLocalizedName((string) $name);
            if ($normalized === '') {
                continue;
            }

            $names[$lang] = $normalized;
        }

        return $names;
    }

    private function normalizeLocalizedName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
    }

    private function originalLocalizedName(string $name): string
    {
        return trim($name);
    }
}
