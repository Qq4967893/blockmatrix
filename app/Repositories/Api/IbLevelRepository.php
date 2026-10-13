<?php

namespace App\Repositories\Api;

use App\Models\Api\IbLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Repositories\Repository;

class IbLevelRepository extends Repository
{
    public function __construct(IbLevel $model)
    {
        parent::__construct($model);
    }

    public function getAll($status)
    {
        return $this->getQuery()->from('ib_level as il')
            ->leftJoin('ib_commission_rule as icr', 'icr.id', '=', 'il.commission_rule_id')
            ->where('il.status', $status)
            ->select('il.*', 'icr.name as commission_rule_name')
            ->get()->toArray();
    }

    /**
     * @return array<int, array{level_pos:int,name:array|string|null}>
     */
    public function getEnabledLevelParams(): array
    {
        return $this->getQuery()
            ->from('ib_level')
            ->where('status', IbLevel::STATUS_ACTIVE)
            ->orderBy('level_pos')
            ->orderBy('id')
            ->get(['level_pos', 'name'])
            ->toArray();
    }

    public function getMaxActiveLevelPos(): int
    {
        return (int) ($this->getQuery()
            ->from('ib_level')
            ->where('status', IbLevel::STATUS_ACTIVE)
            ->max('level_pos') ?? 0);
    }

    /**
     * @param array{
     *     level_pos?: int|null,
     *     name?: string|null
     * } $filters
     * @return array{0: array<int, array>, 1: int}
     */
    public function getList(array $filters, int $limit, int $offset): array
    {
        $query = $this->getQuery()
            ->from('ib_level')
            ->select([
                'ib_level.*',
                'ib_commission_rule.name as commission_rule_name',
                'current_version.base_commission_rate as commission_rule_base_commission_rate',
            ])
            ->leftJoin('ib_commission_rule', 'ib_commission_rule.id', '=', 'ib_level.commission_rule_id')
            ->leftJoin('ib_commission_rule_version as current_version', 'current_version.id', '=', 'ib_commission_rule.current_version_id')
            ->where('ib_level.status', IbLevel::STATUS_ACTIVE);

        if (($filters['level_pos'] ?? null) !== null) {
            $query->where('ib_level.level_pos', (int) $filters['level_pos']);
        }

        if (($filters['name'] ?? '') !== '') {
            $this->appendJsonNameKeywordFilter($query, (string) $filters['name'], 'ib_level.name');
        }

        $total = (clone $query)->count('ib_level.id');
        $data = $query->orderBy('ib_level.level_pos')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        return [$data, $total];
    }

    public function findById(int $levelId): ?IbLevel
    {
        return $this->getQuery()->find($levelId);
    }

    public function findActiveById(int $levelId): ?IbLevel
    {
        return $this->getQuery()
            ->where('id', $levelId)
            ->where('status', IbLevel::STATUS_ACTIVE)
            ->first();
    }

    public function findTopLevel(): ?IbLevel
    {
        return $this->getQuery()
            ->where('status', IbLevel::STATUS_ACTIVE)
            ->where(function (Builder $query): void {
                $query->where('level_pos', IbLevel::ROOT_LEVEL_POS)
                    ->orWhere('is_root', IbLevel::IS_ROOT_YES);
            })
            ->orderByDesc('is_root')
            ->orderBy('level_pos')
            ->orderBy('id')
            ->first();
    }

    public function createLevel(array $payload): IbLevel
    {
        /** @var IbLevel $level */
        $level = $this->getQuery()->create($payload);

        return $level;
    }

    public function updateLevel(int $levelId, array $payload): ?IbLevel
    {
        $level = $this->findById($levelId);
        if (! $level) {
            return null;
        }

        $level->fill($payload);
        $level->save();

        return $level->fresh();
    }

    public function deleteLevel(int $levelId): ?IbLevel
    {
        $level = $this->findById($levelId);
        if (! $level) {
            return null;
        }

        $level->delete();

        return $level;
    }

    public function getNextLevelPosForCreate(): int
    {
        $maxPos = (int) ($this->getQuery()
            ->where('status', IbLevel::STATUS_ACTIVE)
            ->lockForUpdate()
            ->max('level_pos') ?? 0);

        return $maxPos + 1;
    }

    /**
     * Lock active levels together with their effective commission rates for ordering.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveLevelsForOrderUpdate(): array
    {
        return $this->getQuery()
            ->from('ib_level as il')
            ->leftJoin('ib_commission_rule as icr', 'icr.id', '=', 'il.commission_rule_id')
            ->leftJoin('ib_commission_rule_version as icrv', 'icrv.id', '=', 'icr.current_version_id')
            ->where('il.status', IbLevel::STATUS_ACTIVE)
            ->orderBy('il.id')
            ->lockForUpdate()
            ->get([
                'il.id',
                'il.name',
                'il.level_pos',
                'il.is_root',
                'il.active_trader_threshold',
                'il.team_position_size_threshold',
                'icrv.base_commission_rate',
                'icrv.status as commission_version_status',
            ])
            ->toArray();
    }

    /**
     * Apply the final order. Temporary positions avoid unique-index conflicts when
     * existing positions are swapped.
     *
     * @param  array<int, array<string, mixed>>  $orderedLevels
     */
    public function applyOrder(array $orderedLevels): void
    {
        $orderedLevels = array_values($orderedLevels);
        $ids = array_values(array_filter(array_map(
            static fn (array $level): int => (int) ($level['id'] ?? 0),
            $orderedLevels
        )));

        if (count($ids) !== count($orderedLevels)) {
            throw new \Exception(trans('public.message.data_error'));
        }

        $this->getQuery()
            ->whereIn('id', $ids)
            ->update(['level_pos' => DB::raw('-id')]);

        $now = now();
        foreach ($orderedLevels as $index => $level) {
            $levelPos = $index + 1;
            $isRoot = $levelPos === IbLevel::ROOT_LEVEL_POS ? IbLevel::IS_ROOT_YES : 0;

            $payload = [
                'level_pos' => $levelPos,
                'is_root' => $isRoot,
                'updated_at' => $now,
            ];

            if ($isRoot === IbLevel::IS_ROOT_YES) {
                $payload['active_trader_threshold'] = 1;
                $payload['team_position_size_threshold'] = '1';
            }

            $this->getQuery()
                ->where('id', (int) $level['id'])
                ->update($payload);
        }
    }

    public function existsByLevelPos(int $levelPos, ?int $ignoreId = null): bool
    {
        $query = $this->getQuery()
            ->where('status', IbLevel::STATUS_ACTIVE)
            ->where('level_pos', $levelPos);
        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function existsByCommissionRuleId(int $commissionRuleId): bool
    {
        return $this->getQuery()
            ->where('commission_rule_id', $commissionRuleId)
            ->where('status', IbLevel::STATUS_ACTIVE)
            ->exists();
    }

    /**
     * Check whether any localized level name conflicts with an existing active level.
     *
     * @param  array<string, string>  $localeMap
     * @return array{level_id:int, conflicts: array<int, array{lang:string,name:string}>}|null
     */
    public function findLocalizedNameConflict(array $localeMap, ?int $ignoreLevelId = null): ?array
    {
        $incomingNames = $this->normalizeLocalizedNameMap($localeMap);
        if (empty($incomingNames)) {
            return null;
        }

        $query = $this->getQuery()
            ->from('ib_level')
            ->select(['id', 'name'])
            ->where('status', IbLevel::STATUS_ACTIVE);

        if ($ignoreLevelId !== null) {
            $query->where('id', '!=', $ignoreLevelId);
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
                    'level_id' => (int) $row->id,
                    'conflicts' => $conflicts,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<int>  $fromRuleIds
     */
    public function rebindCommissionRuleIds(array $fromRuleIds, int $toRuleId): void
    {
        if (empty($fromRuleIds)) {
            return;
        }

        $this->getQuery()
            ->whereIn('commission_rule_id', $fromRuleIds)
            ->update([
                'commission_rule_id' => $toRuleId,
            ]);
    }

    private function appendJsonNameKeywordFilter(Builder $query, string $keyword, string $column): void
    {
        $like = $this->like($keyword);
        $langs = $this->getLangKeys();

        $query->where(function (Builder $builder) use ($column, $langs, $like) {
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
