<?php

namespace App\Services\Api;

use App\Repositories\Api\IbAccountRepository;
use App\Repositories\Api\IbCommissionRuleRepository;
use App\Repositories\Api\IbLevelRepository;
use Illuminate\Support\Facades\DB;

class IbLevelService
{
    public function __construct(
        private IbLevelRepository $levelRepo,
        private IbCommissionRuleRepository $commissionRuleRepo,
        private IbAccountRepository $ibAccountRepo,
        private IbLevelOrderService $levelOrderService,
    ) {}

    /**
     * @param array{
     *     level_pos?: int|null,
     *     name?: string|null
     * } $filters
     * @return array{
     *     data: array<int, array{
     *         id:int,
     *         level_pos:int,
     *         name:string,
     *         locale_configs:array<int, array{lang:string,name:string}>,
     *         commission_rule_id:int,
     *         commission_rule_name:string,
     *         commission_rule_base_commission_rate:string,
     *         is_initial:int,
     *         is_initial_text:string,
     *         active_trader_threshold:int,
     *         team_position_size_threshold:string,
     *         assessment_rule_text:string,
     *         rule_locked:int,
     *         created_at:string,
     *         updated_at:string
     *     }>,
     *     total_count: int,
     *     next_level_id: int
     * }
     */
    public function getList(array $filters, int $limit, int $offset): array
    {
        [$rows, $count] = $this->levelRepo->getList($filters, $limit, $offset);
        $nextLevelId = $this->levelRepo->getMaxActiveLevelPos() + 1;

        $lang = $this->currentLang();
        $data = array_map(function (array $row) use ($lang): array {
            $activeTraderThreshold = (int) ($row['active_trader_threshold'] ?? 0);
            $teamPositionSizeThreshold = $this->formatDecimal($row['team_position_size_threshold'] ?? 0);
            $isInitial = (int) ($row['is_initial'] ?? 0);

            // 代理等级（level_pos=1）不可修改等级考核规则（活跃客户数，团队持仓量），前端可据此禁用对应编辑能力。
            $ruleLocked = 0;
            if ($row['level_pos'] === 1) {
                $ruleLocked = 1;
            }

            return [
                'id' => (int) $row['id'],
                'level_pos' => (int) $row['level_pos'],
                'name' => $this->resolveLocalizedName($row['name'] ?? [], $lang),
                'locale_configs' => $this->buildLocaleConfigs($row['name'] ?? []),
                'commission_rule_id' => (int) ($row['commission_rule_id'] ?? 0),
                'commission_rule_name' => $this->resolveLocalizedName($row['commission_rule_name'] ?? [], $lang),
                'commission_rule_base_commission_rate' => $this->formatRatePercentage($row['commission_rule_base_commission_rate'] ?? '0'),
                'is_initial' => $isInitial,
                'is_initial_text' => $this->booleanText($isInitial),
                'active_trader_threshold' => $activeTraderThreshold,
                'team_position_size_threshold' => $teamPositionSizeThreshold,
                'assessment_rule_text' => sprintf(
                    '活跃客户数>=%d，团队持仓量>=%s',
                    $activeTraderThreshold,
                    $this->trimTrailingZeros($teamPositionSizeThreshold)
                ),
                'rule_locked' => $ruleLocked,
                'created_at' => $this->formatDateTime($row['created_at'] ?? null),
                'updated_at' => $this->formatDateTime($row['updated_at'] ?? null),
            ];
        }, $rows);

        return [
            'data' => $data,
            'total_count' => $count,
            'next_level_id' => $nextLevelId,
        ];
    }

    /**
     * @return array<int, array{id:int,name:string}>
     */
    public function getCommissionRuleOptions(): array
    {
        // 只展示未与代理等级绑定的佣金规则（一条等级规则只能绑定一条佣金配置）
        $lang = $this->currentLang();
        $rows = $this->commissionRuleRepo->getEffectiveOptions();

        return array_map(static function (array $row) use ($lang): array {
            return [
                'id' => (int) $row['id'],
                'name' => self::resolveStaticLocalizedName($row['name'] ?? [], $lang),
            ];
        }, $rows);
    }

    public function store(array $payload): array
    {
        [$localeMap, $commissionRuleId, $isInitial, $activeTraderThreshold, $teamPositionSizeThreshold] = $this->normalizeStorePayload($payload);
        $conflict = $this->levelRepo->findLocalizedNameConflict($localeMap);
        if ($conflict !== null) {
            return [
                'is_succ' => false,
                'message' => $this->buildLocalizedNameConflictMessage($conflict),
            ];
        }

        // 判断该佣金规则是否已被其他代理等级绑定，如果已被绑定该佣金规则不可用（一条代理等级只能绑定一条佣金规则）
        $ibLevelAssigned = $this->levelRepo->existsByCommissionRuleId($commissionRuleId);
        if ($ibLevelAssigned) {
            return [
                'is_succ' => false,
                'message' => trans('ib_level.message.ib_commission_rule_assigned'),
            ];
        }

        $this->getEffectiveCommissionRuleRate($commissionRuleId);

        $errorMessage = null;
        DB::connection(DB_API)->transaction(function () use (
            $localeMap,
            $commissionRuleId,
            $isInitial,
            $activeTraderThreshold,
            $teamPositionSizeThreshold,
            &$errorMessage,
        ): void {
            // Lock all active levels first so concurrent creates cannot choose
            // the same commission rule or provisional position.
            $levels = $this->levelRepo->getActiveLevelsForOrderUpdate();
            if ($this->levelRepo->existsByCommissionRuleId($commissionRuleId)) {
                throw new \Exception(trans('ib_level.message.ib_commission_rule_assigned'));
            }

            $commissionRuleRate = $this->getEffectiveCommissionRuleRate($commissionRuleId);
            $orderedLevels = $this->levelOrderService->planOrder($levels);
            $targetLevelPos = $this->levelOrderService->resolveInsertPosition($levels, $commissionRuleRate);
            $stepError = $this->validateThresholdStepForOrderPosition(
                $orderedLevels,
                $targetLevelPos,
                $activeTraderThreshold,
                $teamPositionSizeThreshold
            );
            if ($stepError !== null) {
                $errorMessage = $stepError;

                return;
            }

            $levelPos = $this->levelRepo->getNextLevelPosForCreate();
            $this->levelRepo->createLevel([
                'name' => $localeMap,
                'level_pos' => $levelPos,
                'is_root' => 0,
                'commission_rule_id' => $commissionRuleId,
                'is_initial' => $isInitial,
                'status' => 1,
                'active_trader_threshold' => $this->normalizeTopLevelThreshold($targetLevelPos, $activeTraderThreshold),
                'team_position_size_threshold' => $this->normalizeTopLevelThreshold($targetLevelPos, $teamPositionSizeThreshold),
            ]);

            $this->levelOrderService->sync();
        });
        if ($errorMessage !== null) {
            return [
                'is_succ' => false,
                'message' => $errorMessage,
            ];
        }

        return [
            'is_succ' => true,
            'message' => '',
        ];
    }

    public function update(int $levelId, array $payload): array
    {
        $level = $this->levelRepo->findActiveById($levelId);
        if (! $level) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        [$localeMap, $commissionRuleId, $isInitial, $activeTraderThreshold, $teamPositionSizeThreshold] = $this->normalizeUpdatePayload($payload);
        $isTopLevel = (int) ($level->is_root ?? 0) === 1 || (int) ($level->level_pos ?? 0) === 1;
        $currentIsInitial = (int) ($level->is_initial ?? 0);
        $isInitial = $this->resolveUpdateIsInitial($payload, $currentIsInitial, $isInitial);
        $conflict = $this->levelRepo->findLocalizedNameConflict($localeMap, $levelId);
        if ($conflict !== null) {
            return [
                'is_succ' => false,
                'message' => $this->buildLocalizedNameConflictMessage($conflict),
            ];
        }

        // 判断该佣金规则是否已被其他代理等级绑定，如果该佣金规则已被绑定，且要修改的佣金规则不是自身绑定的佣金规则不可修改（一条代理等级只能绑定一条佣金规则）
        $ibLevelAssigned = $this->levelRepo->existsByCommissionRuleId($commissionRuleId);
        if ($ibLevelAssigned && $commissionRuleId != $level->commission_rule_id) {
            return [
                'is_succ' => false,
                'message' => trans('ib_level.message.ib_commission_rule_assigned'),
            ];
        }

        $this->getEffectiveCommissionRuleRate($commissionRuleId);

        $errorMessage = null;
        DB::connection(DB_API)->transaction(function () use (
            $levelId,
            $localeMap,
            $commissionRuleId,
            $isInitial,
            $activeTraderThreshold,
            $teamPositionSizeThreshold,
            &$errorMessage,
        ): void {
            $levels = $this->levelRepo->getActiveLevelsForOrderUpdate();
            $currentLevel = $this->levelRepo->findActiveById($levelId);
            if (! $currentLevel) {
                throw new \Exception(trans('public.message.non_existent'));
            }

            if ($this->levelRepo->existsByCommissionRuleId($commissionRuleId)
                && (int) $currentLevel->commission_rule_id !== $commissionRuleId
            ) {
                throw new \Exception(trans('ib_level.message.ib_commission_rule_assigned'));
            }

            $this->getEffectiveCommissionRuleRate($commissionRuleId);
            $commissionRuleChanged = (int) $currentLevel->commission_rule_id !== $commissionRuleId;
            $thresholdChanged = $this->thresholdChanged(
                $currentLevel,
                $activeTraderThreshold,
                $teamPositionSizeThreshold
            );

            if (! $commissionRuleChanged) {
                $orderedLevels = $this->levelOrderService->planOrder($levels);
                $currentOrderPosition = $this->findLevelOrderPosition($orderedLevels, $levelId);
                $isTopLevel = $currentOrderPosition === 1;

                if ($thresholdChanged) {
                    $stepError = $this->validateThresholdStepForOrderPosition(
                        $orderedLevels,
                        $currentOrderPosition,
                        $activeTraderThreshold,
                        $teamPositionSizeThreshold,
                        $levelId
                    );
                    if ($stepError !== null) {
                        $errorMessage = $stepError;

                        return;
                    }
                }
            } else {
                $isTopLevel = (int) ($currentLevel->is_root ?? 0) === 1
                    || (int) ($currentLevel->level_pos ?? 0) === 1;
            }

            $updatePayload = [
                'name' => $localeMap,
                'commission_rule_id' => $commissionRuleId,
                'is_initial' => $isInitial,
            ];

            if ($isTopLevel) {
                $updatePayload['active_trader_threshold'] = 1;
                $updatePayload['team_position_size_threshold'] = '1';
            } else {
                $updatePayload['active_trader_threshold'] = $activeTraderThreshold;
                $updatePayload['team_position_size_threshold'] = $teamPositionSizeThreshold;
            }

            $updated = $this->levelRepo->updateLevel($levelId, $updatePayload);

            if (! $updated) {
                throw new \Exception(trans('public.message.non_existent'));
            }

            if ($commissionRuleChanged) {
                $this->levelOrderService->sync();
            }
        });
        if ($errorMessage !== null) {
            return [
                'is_succ' => false,
                'message' => $errorMessage,
            ];
        }

        return [
            'is_succ' => true,
            'message' => '',
        ];
    }

    public function destroy(int $levelId): void
    {
        $level = $this->levelRepo->findActiveById($levelId);
        if (! $level) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        if ((int) ($level->is_root ?? 0) === 1 || (int) ($level->level_pos ?? 0) === 1) {
            throw new \Exception(trans('public.message.ib_level_root_locked'));
        }

        if ($this->ibAccountRepo->existsByLevelId($levelId)) {
            throw new \Exception(trans('public.message.ib_level_account_exists'));
        }

        DB::connection(DB_API)->transaction(function () use ($levelId): void {
            $deleted = $this->levelRepo->deleteLevel($levelId);
            if (! $deleted) {
                throw new \Exception(trans('public.message.non_existent'));
            }

            $this->levelOrderService->sync();
        });
    }

    /**
     * @return array{0: array<string, string>, 1: int, 2: int, 3: int, 4: string}
     */
    private function normalizeStorePayload(array $payload): array
    {
        $localeMap = $this->normalizeLocaleConfigs($payload['locale_configs'] ?? []);
        $commissionRuleId = (int) ($payload['commission_rule_id'] ?? 0);
        $isInitial = $this->normalizeBooleanInt($payload['is_initial'] ?? 0);
        $activeTraderThreshold = (int) ($payload['active_trader_threshold'] ?? 0);
        $teamPositionSizeThreshold = $this->normalizeDecimal($payload['team_position_size_threshold'] ?? null);

        if ($commissionRuleId <= 0 || $activeTraderThreshold < 0) {
            throw new \Exception(trans('public.message.data_error'));
        }

        return [
            $localeMap,
            $commissionRuleId,
            $isInitial,
            $activeTraderThreshold,
            $teamPositionSizeThreshold,
        ];
    }

    /**
     * @return array{0: array<string, string>, 1: int, 2: int, 3: int, 4: string}
     */
    private function normalizeUpdatePayload(array $payload): array
    {
        $localeMap = $this->normalizeLocaleConfigs($payload['locale_configs'] ?? []);
        $commissionRuleId = (int) ($payload['commission_rule_id'] ?? 0);
        $isInitial = $this->normalizeBooleanInt($payload['is_initial'] ?? 0);
        $activeTraderThreshold = (int) ($payload['active_trader_threshold'] ?? 0);
        $teamPositionSizeThreshold = $this->normalizeDecimal($payload['team_position_size_threshold'] ?? null);

        if ($commissionRuleId <= 0 || $activeTraderThreshold < 0) {
            throw new \Exception(trans('public.message.data_error'));
        }

        return [
            $localeMap,
            $commissionRuleId,
            $isInitial,
            $activeTraderThreshold,
            $teamPositionSizeThreshold,
        ];
    }

    /**
     * Top-level levels are fixed to 1/1.
     */
    private function normalizeTopLevelThreshold(int $levelPos, int|string $value): int|string
    {
        if ($levelPos === 1) {
            return is_string($value) ? '1' : 1;
        }

        return $value;
    }

    /**
     * @param  array<int, array{lang:string,name:string}>  $localeConfigs
     * @return array<string, string>
     */
    private function normalizeLocaleConfigs(array $localeConfigs): array
    {
        if (empty($localeConfigs)) {
            throw new \Exception(trans('public.message.in_complete'));
        }

        $names = [];
        foreach ($localeConfigs as $config) {
            $lang = (string) ($config['lang'] ?? '');
            $name = trim((string) ($config['name'] ?? ''));

            if ($lang === '' || $name === '') {
                throw new \Exception(trans('public.message.in_complete'));
            }

            if (isset($names[$lang])) {
                throw new \Exception(trans('public.message.repeat_lang'));
            }

            $names[$lang] = $name;
        }

        if (! isset($names['en_US'])) {
            throw new \Exception(trans('public.message.default_lang'));
        }

        return $names;
    }

    private function getEffectiveCommissionRuleRate(int $commissionRuleId): string
    {
        $rule = $this->commissionRuleRepo->findById($commissionRuleId);
        if (! $rule) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        $rate = $this->commissionRuleRepo->getEffectiveBaseCommissionRate($commissionRuleId);
        if ($rate === null) {
            throw new \Exception(trans('public.message.state_error'));
        }

        return $rate;
    }

    private function thresholdChanged(object $level, int $activeTraderThreshold, string $teamPositionSizeThreshold): bool
    {
        return $activeTraderThreshold !== (int) ($level->active_trader_threshold ?? 0)
            || $this->compareDecimalToInt(
                $teamPositionSizeThreshold,
                $this->formatDecimal($level->team_position_size_threshold ?? 0)
            ) !== 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $orderedLevels
     */
    private function findLevelOrderPosition(array $orderedLevels, int $levelId): int
    {
        foreach ($orderedLevels as $index => $level) {
            if ((int) ($level['id'] ?? 0) === $levelId) {
                return $index + 1;
            }
        }

        throw new \Exception(trans('public.message.non_existent'));
    }

    /**
     * @param  array{level_id:int,conflicts: array<int, array{lang:string,name:string}>}  $conflict
     */
    private function buildLocalizedNameConflictMessage(array $conflict): string
    {
        $parts = array_map(
            static fn (array $item): string => sprintf('%s=%s', $item['lang'], $item['name']),
            $conflict['conflicts']
        );

        return trans('ib_level.message.name_exists', [
            'details' => implode(', ', $parts),
        ]);
    }

    /**
     * Validate a manually entered threshold against its rate-derived neighbors.
     *
     * @param  array<int, array<string, mixed>>  $orderedLevels
     */
    private function validateThresholdStepForOrderPosition(
        array $orderedLevels,
        int $levelPos,
        int $activeTraderThreshold,
        string $teamPositionSizeThreshold,
        ?int $ignoreLevelId = null
    ): ?string {
        if ($levelPos <= 1) {
            return null;
        }

        $previousIndex = $levelPos - 2;
        while ($previousIndex >= 0 && $ignoreLevelId !== null && (int) ($orderedLevels[$previousIndex]['id'] ?? 0) === $ignoreLevelId) {
            $previousIndex--;
        }
        $previousLevel = $previousIndex >= 0 ? $orderedLevels[$previousIndex] : null;
        if (! $previousLevel) {
            return null;
        }

        $previousActiveTraderThreshold = (int) ($previousLevel['active_trader_threshold'] ?? 0);
        $previousTeamPositionSizeThreshold = $this->formatDecimal($previousLevel['team_position_size_threshold'] ?? 0);

        if ($activeTraderThreshold <= $previousActiveTraderThreshold || $this->compareDecimalToInt($teamPositionSizeThreshold, $previousTeamPositionSizeThreshold) <= 0) {
            return trans('ib_level.message.threshold_greater_previous', [
                'prev_active_trader_threshold' => $previousActiveTraderThreshold,
                'prev_team_position_size_threshold' => $this->trimTrailingZeros($previousTeamPositionSizeThreshold),
            ]);
        }

        return null;
    }

    private function currentLang(): string
    {
        return (string) (app()->getLocale() ?: config('app.locale', 'en_US'));
    }

    private function booleanText(int $value): string
    {
        return (string) trans('user.boolean.'.($value === 1 ? 'yes' : 'no'));
    }

    /**
     * @param  array<string, string>|string|null  $value
     */
    private function resolveLocalizedName(array|string|null $value, string $lang): string
    {
        return self::resolveStaticLocalizedName($value, $lang);
    }

    /**
     * @param  array<string, string>|string|null  $value
     */
    private static function resolveStaticLocalizedName(array|string|null $value, string $lang): string
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
     * @return array<int, array{lang:string,name:string}>
     */
    private function buildLocaleConfigs(array|string|null $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $configs = [];
        foreach ($value as $lang => $name) {
            if ($name === null || $name === '') {
                continue;
            }

            $configs[] = [
                'lang' => (string) $lang,
                'name' => (string) $name,
            ];
        }

        return $configs;
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_numeric($value)) {
            return date('Y-m-d H:i:s', (int) $value);
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function normalizeDecimal(mixed $value): string
    {
        if (! is_numeric($value)) {
            throw new \Exception(trans('public.message.data_error'));
        }

        return number_format((float) $value, 6, '.', '');
    }

    private function normalizeBooleanInt(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value)) {
            return $value === 1 ? 1 : 0;
        }

        if (is_string($value)) {
            if ($value === '1') {
                return 1;
            }

            if ($value === '0') {
                return 0;
            }
        }

        throw new \Exception(trans('public.message.data_error'));
    }

    private function resolveUpdateIsInitial(array $payload, int $currentIsInitial, int $incomingIsInitial): int
    {
        if (! array_key_exists('is_initial', $payload) || $payload['is_initial'] === null) {
            return $currentIsInitial;
        }

        return $incomingIsInitial;
    }

    private function formatDecimal(mixed $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }

    private function formatRatePercentage(mixed $value): string
    {
        return $this->trimTrailingZeros(number_format((float) $value, 4, '.', ''));
    }

    private function compareDecimalToInt(string $left, string $right): int
    {
        $leftMicros = (int) round(((float) $left) * 1000000);
        $rightMicros = (int) round(((float) $right) * 1000000);

        return $leftMicros <=> $rightMicros;
    }

    private function trimTrailingZeros(string $value): string
    {
        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
