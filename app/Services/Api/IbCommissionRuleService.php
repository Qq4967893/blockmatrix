<?php

namespace App\Services\Api;

use App\Models\Api\Money;
use App\Models\Api\Source;
use App\Repositories\Api\IbCommissionRuleDetailRepository;
use App\Repositories\Api\IbCommissionRuleRepository;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IbCommissionRuleService
{
    public function __construct(
        private IbCommissionRuleRepository $ruleRepo,
        private IbCommissionRuleDetailRepository $detailRepo,
        private IbCommissionSourceDetailFormatter $sourceDetailFormatter,
        private IbLevelOrderService $levelOrderService,
    ) {}

    /**
     * @param array{
     *     name?: string|null,
     *     level_name?: string|null,
     *     is_effective?: int|null,
     *     review_status?: int|null
     * } $filters
     * @return array{data: array<int, array>, total_count: int}
     */
    public function getList(array $filters, int $limit, int $offset): array
    {
        [$rows, $count] = $this->ruleRepo->getList($filters, $limit, $offset);

        if (empty($rows)) {
            return [
                'data' => [],
                'total_count' => 0,
            ];
        }

        $lang = $this->currentLang();
        $ruleIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $levelNamesMap = $this->ruleRepo->getLinkedLevelNamesByRuleIds($ruleIds, $lang);
        $levelCountMap = $this->ruleRepo->getLinkedLevelCounts($ruleIds);

        $data = array_map(function (array $row) use ($lang, $levelNamesMap, $levelCountMap): array {
            $id = (int) $row['id'];
            $linkedLevelCount = (int) ($levelCountMap[$id] ?? 0);
            $isEffective = $linkedLevelCount > 0 ? 1 : 0;
            $apiStatus = $this->toApiReviewStatus((int) ($row['version_status'] ?? IbCommissionRuleRepository::STATUS_DRAFT));

            return [
                'id' => $id,
                'name' => $this->resolveLocalizedName($row['name'] ?? [], $lang),
                'locale_configs' => $this->buildLocaleConfigs($row['name'] ?? []),
                'level_name' => implode('、', $levelNamesMap[$id] ?? []),
                'base_commission_rate' => $this->formatRatePercentage($row['base_commission_rate'] ?? '0'),
                'promotion_commission_rate' => $this->formatRatePercentage($row['promotion_commission_rate'] ?? '0'),
                'created_at' => $this->formatDateTime($row['created_at'] ?? null),
                'reviewed_at' => $this->formatDateTime($row['reviewed_at'] ?? null),
                'review_status' => $apiStatus,
                'review_status_text' => trans('ib_commission_rule.review_status.'.$apiStatus),
                'effective_status' => $isEffective,
                'effective_status_text' => trans('ib_commission_rule.effective_status.'.$isEffective),
                'rejection_reason' => $row['rejection_reason'] ?: null,
                'can_delete' => $linkedLevelCount > 0 ? 0 : 1,
            ];
        }, $rows);

        return [
            'data' => $data,
            'total_count' => $count,
        ];
    }

    /**
     * 获取佣金规则详情（包含各交易品种的佣金配置）
     *
     * @return array{
     *     current_lang:string,
     *     rule_name:string,
     *     base_commission_rate:string,
     *     promotion_commission_rate:string,
     *     data: array<int, array{
     *         source:string,
     *         source_name:string,
     *         source_status:int,
     *         is_source_deleted:bool,
     *         settlement_unit:?string,
     *         commission_amount:string,
     *         commission_fee_rate:string,
     *         is_support_open_close_fee:bool,
     *         direction_details: array{
     *             buy: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string},
     *             sell: array{open_fee_rate:?string,open_fee:?string,close_fee_rate:?string,close_fee:?string}
     *         }
     *     }>
     * }
     */
    public function getDetail(int $ruleId, ?string $source = null): array
    {
        $rule = $this->findRuleOrFail($ruleId);
        $version = $this->ruleRepo->findLatestVersionByRuleId($ruleId);

        $lang = $this->currentLang();
        $detailRows = $version ? $this->detailRepo->listByRuleVersionId((int) $version->id) : [];
        $detailMap = [];
        foreach ($detailRows as $row) {
            $detailMap[(string) $row['source']] = $row;
        }

        $sourceQuery = Source::query()->orderBy('source');
        if ($source !== null && $source !== '') {
            $sourceQuery->where('source', $source);
        }

        $sources = $sourceQuery->get(['source', 'name', 'config', 'status'])->keyBy('source');
        $sourceCodes = array_values(array_unique(array_merge(array_keys($detailMap), $sources->keys()->all())));

        usort($sourceCodes, function (string $left, string $right) use ($detailMap, $sources): int {
            $leftIsNew = $sources->has($left) && ! isset($detailMap[$left]);
            $rightIsNew = $sources->has($right) && ! isset($detailMap[$right]);

            if ($leftIsNew !== $rightIsNew) {
                return $leftIsNew ? -1 : 1;
            }

            return strcmp($left, $right);
        });

        $data = collect($sourceCodes)->map(function (string $sourceCode) use ($lang, $detailMap, $sources): array {
            /** @var Source|null $sourceItem */
            $sourceItem = $sources->get($sourceCode);
            $detail = $detailMap[$sourceCode] ?? null;

            return $sourceItem
                ? $this->sourceDetailFormatter->format($sourceItem, $lang, $detail)
                : $this->sourceDetailFormatter->formatDeleted($sourceCode, $detail);
        })->values()->all();

        return [
            'current_lang' => $lang,
            'rule_name' => $this->resolveLocalizedName($rule->name ?? [], $lang),
            'base_commission_rate' => $this->formatRatePercentage($version?->base_commission_rate ?? '0'),
            'promotion_commission_rate' => $this->formatRatePercentage($version?->promotion_commission_rate ?? '0'),
            'data' => $data,
        ];
    }

    public function store(array $payload): void
    {
        [$localeMap, $baseCommissionRate, $promotionCommissionRate, $details] = $this->normalizeRulePayload($payload);
        $this->assertLocalizedRuleNameUnique($localeMap);
        $this->assertBaseCommissionRateAvailable($baseCommissionRate);
        $details = $this->validateAndFillSourceDetails($details);

        DB::connection(DB_API)->transaction(function () use ($localeMap, $baseCommissionRate, $promotionCommissionRate, $details): void {
            $rule = $this->ruleRepo->createRule([
                'name' => $localeMap,
                'current_version_id' => null,
            ]);

            $version = $this->ruleRepo->createVersion([
                'rule_id' => (int) $rule->id,
                'version_no' => 1,
                'base_commission_rate' => $baseCommissionRate,
                'promotion_commission_rate' => $promotionCommissionRate,
                ...$this->makePendingReviewPayload(),
                'remark' => null,
            ]);

            $this->detailRepo->syncByRuleVersionId((int) $version->id, $details);
        });
    }

    public function update(int $ruleId, array $payload): void
    {
        $rule = $this->findRuleOrFail($ruleId);
        [$localeMap, $baseCommissionRate, $promotionCommissionRate, $details] = $this->normalizeRulePayload($payload);
        $this->assertLocalizedRuleNameUnique($localeMap, $ruleId);
        $this->assertBaseCommissionRateAvailable($baseCommissionRate, $ruleId);
        $details = $this->validateAndFillSourceDetails($details);

        DB::connection(DB_API)->transaction(function () use ($ruleId, $rule, $localeMap, $baseCommissionRate, $promotionCommissionRate, $details): void {
            $this->ruleRepo->lockEffectiveRules();
            $this->assertBaseCommissionRateAvailable($baseCommissionRate, $ruleId);
            $latestVersion = $this->ruleRepo->findLatestVersionByRuleId($ruleId);

            $this->ruleRepo->updateRule((int) $rule->id, [
                'name' => $localeMap,
            ]);

            $latestStatus = (int) ($latestVersion?->status ?? IbCommissionRuleRepository::STATUS_DRAFT);
            if ($latestVersion && in_array($latestStatus, [
                IbCommissionRuleRepository::STATUS_DRAFT,
                IbCommissionRuleRepository::STATUS_PENDING_REVIEW,
            ], true)) {
                $this->ruleRepo->updateVersion((int) $latestVersion->id, [
                    'base_commission_rate' => $baseCommissionRate,
                    'promotion_commission_rate' => $promotionCommissionRate,
                    ...$this->makePendingReviewPayload(),
                ]);

                $this->detailRepo->syncByRuleVersionId((int) $latestVersion->id, $details);

                return;
            }

            $nextVersionNo = (int) (($latestVersion?->version_no ?? 0) + 1);

            $newVersion = $this->ruleRepo->createVersion([
                'rule_id' => $ruleId,
                'version_no' => $nextVersionNo,
                'base_commission_rate' => $baseCommissionRate,
                'promotion_commission_rate' => $promotionCommissionRate,
                ...$this->makePendingReviewPayload(),
                'remark' => null,
            ]);

            $this->detailRepo->syncByRuleVersionId((int) $newVersion->id, $details);
        });
    }

    public function review(int $ruleId, int $action, ?string $rejectionReason): void
    {
        $rule = $this->findRuleOrFail($ruleId);
        $latestVersion = $this->ruleRepo->findLatestVersionByRuleId($ruleId);
        if (! $latestVersion) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        $currentStatus = (int) ($latestVersion->status ?? IbCommissionRuleRepository::STATUS_DRAFT);
        if (! in_array($currentStatus, [IbCommissionRuleRepository::STATUS_DRAFT, IbCommissionRuleRepository::STATUS_PENDING_REVIEW], true)) {
            throw new \Exception(trans('public.message.state_error'));
        }

        $payload = match ($action) {
            IbCommissionRuleRepository::REVIEW_ACTION_APPROVE => [
                'status' => IbCommissionRuleRepository::STATUS_APPROVED,
                'rejection_reason' => null,
                'activated_at' => now(),
            ],
            IbCommissionRuleRepository::REVIEW_ACTION_REJECT => [
                'status' => IbCommissionRuleRepository::STATUS_REJECTED,
                'rejection_reason' => $rejectionReason,
            ],
            default => throw new \Exception(trans('public.message.data_error')),
        };

        DB::connection(DB_API)->transaction(function () use ($rule, $latestVersion, $payload): void {
            $this->ruleRepo->lockEffectiveRules();

            if ((int) $payload['status'] === IbCommissionRuleRepository::STATUS_APPROVED) {
                $this->assertEffectiveBaseCommissionRateAvailable(
                    (string) $latestVersion->base_commission_rate,
                    (int) $rule->id
                );
            }

            $this->ruleRepo->updateVersion((int) $latestVersion->id, [
                ...$payload,
                ...$this->makeReviewedPayload(),
            ]);

            if ((int) $payload['status'] !== IbCommissionRuleRepository::STATUS_APPROVED) {
                return;
            }

            $this->ruleRepo->markOtherApprovedVersionsDisabled((int) $rule->id, (int) $latestVersion->id);
            $this->ruleRepo->updateRule((int) $rule->id, [
                'current_version_id' => (int) $latestVersion->id,
            ]);

            if ($this->ruleRepo->existsActiveLinkedLevel((int) $rule->id)) {
                $this->levelOrderService->sync();
            }
        });
    }

    public function destroy(int $ruleId): void
    {
        $this->findRuleOrFail($ruleId);
        if ($this->ruleRepo->existsLinkedLevels($ruleId)) {
            throw new \Exception(trans('public.message.correlation'));
        }

        DB::connection(DB_API)->transaction(function () use ($ruleId): void {
            $this->ruleRepo->deleteRuleWithVersions($ruleId);
        });
    }

    /**
     * @return array{0: array<string, string>, 1: string, 2: string, 3: array<int, array{source:string,position_size_threshold:string,commission_amount:string,commission_fee_rate:string}>}
     */
    private function normalizeRulePayload(array $payload): array
    {
        return [
            $this->normalizeLocaleConfigs($payload['locale_configs'] ?? []),
            $this->normalizeRatePercentage($payload['base_commission_rate'] ?? null),
            $this->normalizeRatePercentage($payload['promotion_commission_rate'] ?? 0),
            $this->normalizeDetails($payload['details'] ?? []),
        ];
    }

    /**
     * @param  array<string, string>  $localeMap
     */
    private function assertLocalizedRuleNameUnique(array $localeMap, ?int $ignoreRuleId = null): void
    {
        $conflict = $this->ruleRepo->findLocalizedNameConflict($localeMap, $ignoreRuleId);
        if ($conflict === null) {
            return;
        }

        throw ValidationException::withMessages([
            'locale_configs' => $this->buildLocalizedNameConflictMessage($conflict),
        ]);
    }

    private function assertBaseCommissionRateAvailable(string $baseCommissionRate, ?int $ignoreRuleId = null): void
    {
        $conflict = $this->ruleRepo->findEffectiveBaseCommissionRateConflict($baseCommissionRate, $ignoreRuleId)
            ?? $this->ruleRepo->findPendingBaseCommissionRateConflict($baseCommissionRate, $ignoreRuleId);

        if ($conflict !== null) {
            throw ValidationException::withMessages([
                'base_commission_rate' => $this->buildBaseCommissionRateConflictMessage($conflict),
            ]);
        }
    }

    private function assertEffectiveBaseCommissionRateAvailable(string $baseCommissionRate, int $ignoreRuleId): void
    {
        $conflict = $this->ruleRepo->findEffectiveBaseCommissionRateConflict($baseCommissionRate, $ignoreRuleId);
        if ($conflict !== null) {
            throw ValidationException::withMessages([
                'base_commission_rate' => $this->buildBaseCommissionRateConflictMessage($conflict),
            ]);
        }
    }

    private function buildBaseCommissionRateConflictMessage(object $conflict): string
    {
        return trans('ib_commission_rule.base_commission_rate_exists', [
            'name' => $this->resolveLocalizedName($conflict->name ?? null, $this->currentLang()),
            'rate' => $this->trimTrailingZeros((string) ($conflict->base_commission_rate ?? '0')),
        ]);
    }

    /**
     * @param  array{rule_id:int,conflicts: array<int, array{lang:string,name:string}>}  $conflict
     */
    private function buildLocalizedNameConflictMessage(array $conflict): string
    {
        $parts = array_map(
            static fn (array $item): string => sprintf('%s=%s', $item['lang'], $item['name']),
            $conflict['conflicts']
        );

        return trans('ib_commission_rule.name_exists', [
            'details' => implode(', ', $parts),
        ]);
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

    /**
     * @param  array<int, array{source:string,position_size_threshold:mixed,commission_amount:mixed,commission_fee_rate:mixed}>  $details
     * @return array<int, array{source:string,position_size_threshold:string,commission_amount:string,commission_fee_rate:string}>
     */
    private function normalizeDetails(array $details): array
    {
        if (empty($details)) {
            throw new \Exception(trans('public.message.in_complete'));
        }

        $normalized = [];
        $seenSources = [];

        foreach ($details as $detail) {
            $source = strtoupper(trim((string) ($detail['source'] ?? '')));
            $threshold = $detail['position_size_threshold'] ?? null;
            $amount = $detail['commission_amount'] ?? null;
            $feeRate = $detail['commission_fee_rate'] ?? null;

            if ($source === '' || $threshold === null) {
                throw new \Exception(trans('public.message.in_complete'));
            }

            if (isset($seenSources[$source])) {
                throw new \Exception(trans('public.message.already_exist'));
            }

            $hasAmount = $amount !== null && $amount !== '';
            $hasFeeRate = $feeRate !== null && $feeRate !== '';
            if ($hasAmount === $hasFeeRate) {
                throw new \Exception(trans('public.message.data_error'));
            }

            $seenSources[$source] = true;
            $normalized[] = [
                'source' => $source,
                'position_size_threshold' => $this->normalizeDecimal($threshold),
                // commission_amount / commission_fee_rate 二选一；未选中的字段写 0 以兼容表结构非空约束。
                'commission_amount' => $hasAmount ? $this->normalizeDecimal($amount) : '0.000000',
                'commission_fee_rate' => $hasFeeRate ? $this->normalizeDecimal($feeRate) : '0.000000',
            ];
        }

        return $normalized;
    }

    /**
     * 校验 details 覆盖所有上架品种，并自动补全缺失的下架品种
     *
     * 规则：
     * - 所有上架（status = STATUS_LISTED 或 status 为 null）的交易品种必须存在于 details 中，
     *   否则抛出异常提示"有部分上架品种未设置"。
     * - 所有下架（status = STATUS_DELISTED）的交易品种如果未在 details 中出现，
     *   则自动补充一条默认记录（各项值为 0），确保数据完整性。
     *
     * @param  array<int, array{source:string,position_size_threshold:string,commission_amount:string,commission_fee_rate:string}>  $details
     * @return array<int, array{source:string,position_size_threshold:string,commission_amount:string,commission_fee_rate:string}>
     */
    private function validateAndFillSourceDetails(array $details): array
    {
        // 获取所有交易品种及其上下架状态
        $sources = Source::query()
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->get(['source', 'status']);

        // 构建已存在品种的查找表（统一转大写以匹配 normalizeDetails 的处理）
        $existingSources = [];
        foreach ($details as $detail) {
            $existingSources[$detail['source']] = true;
        }

        // 构建来源品种的状态映射（key 统一转大写）
        $sourceStatusMap = [];
        foreach ($sources as $source) {
            $code = strtoupper(trim((string) $source->source));
            $sourceStatusMap[$code] = (int) ($source->status ?? Source::STATUS_LISTED);
        }

        // 1. 校验上架品种必须全部存在
        $missingListedSources = [];
        foreach ($sourceStatusMap as $sourceCode => $status) {
            if ($status !== Source::STATUS_LISTED) {
                continue;
            }
            if (! isset($existingSources[$sourceCode])) {
                $missingListedSources[] = $sourceCode;
            }
        }

        if (! empty($missingListedSources)) {
            throw new \Exception(trans('ib_commission_rule.missing_listed_sources'));
        }

        // 2. 补全下架品种（未在 details 中的自动补充默认值）
        $defaultPositionSizeThreshold = number_format(0, Money::AMOUNT_DECIMAL_PLACES, '.', '');
        $defaultCommissionAmount = number_format(0, Money::AMOUNT_DECIMAL_PLACES, '.', '');
        $defaultCommissionFeeRate = number_format(0, Money::AMOUNT_DECIMAL_PLACES, '.', '');

        foreach ($sourceStatusMap as $sourceCode => $status) {
            if ($status !== Source::STATUS_DELISTED) {
                continue;
            }
            if (isset($existingSources[$sourceCode])) {
                continue;
            }
            $details[] = [
                'source' => $sourceCode,
                'position_size_threshold' => $defaultPositionSizeThreshold,
                'commission_amount' => $defaultCommissionAmount,
                'commission_fee_rate' => $defaultCommissionFeeRate,
            ];
        }

        return $details;
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
     * @param  array<string, string>|string|null  $value
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

    private function currentLang(): string
    {
        return (string) (app()->getLocale() ?: config('app.locale', 'en_US'));
    }

    private function findRuleOrFail(int $ruleId): object
    {
        $rule = $this->ruleRepo->findById($ruleId);
        if (! $rule) {
            throw new \Exception(trans('public.message.non_existent'));
        }

        return $rule;
    }

    /**
     * @return array{status:int,submitted_at:mixed,submitted_by:int,reviewed_at:null,reviewed_by:null,rejection_reason:null,activated_at:null}
     */
    private function makePendingReviewPayload(): array
    {
        return [
            'status' => IbCommissionRuleRepository::STATUS_PENDING_REVIEW,
            'submitted_at' => now(),
            'submitted_by' => $this->operatorId(),
            'reviewed_at' => null,
            'reviewed_by' => null,
            'rejection_reason' => null,
            'activated_at' => null,
        ];
    }

    /**
     * @return array{reviewed_at:mixed,reviewed_by:int}
     */
    private function makeReviewedPayload(): array
    {
        return [
            'reviewed_at' => now(),
            'reviewed_by' => $this->operatorId(),
        ];
    }

    private function operatorId(): int
    {
        return (int) (Auth::id() ?? 0);
    }

    private function toApiReviewStatus(int $dbStatus): int
    {
        return match ($dbStatus) {
            IbCommissionRuleRepository::STATUS_DRAFT => 0,
            IbCommissionRuleRepository::STATUS_PENDING_REVIEW => 1,
            IbCommissionRuleRepository::STATUS_APPROVED => 2,
            IbCommissionRuleRepository::STATUS_REJECTED => 3,
            IbCommissionRuleRepository::STATUS_DISABLED => 4,
            default => 0,
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

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $timestamp = (int) $value;

            return $timestamp > 0 ? date(Money::DATETIME_FORMAT, $timestamp) : null;
        }

        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date(Money::DATETIME_FORMAT, $timestamp);
    }

    private function normalizeDecimal(mixed $value): string
    {
        if (! is_numeric($value)) {
            throw new \Exception(trans('public.message.data_error'));
        }

        return number_format((float) $value, Money::AMOUNT_DECIMAL_PLACES, '.', '');
    }

    private function normalizeRatePercentage(mixed $value): string
    {
        if (! is_numeric($value)) {
            throw new \Exception(trans('public.message.data_error'));
        }

        return number_format((float) $value, 4, '.', '');
    }

    private function formatRatePercentage(mixed $value): string
    {
        return $this->trimTrailingZeros(number_format((float) $value, 4, '.', ''));
    }

    private function trimTrailingZeros(string $value): string
    {
        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
