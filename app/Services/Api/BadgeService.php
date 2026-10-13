<?php

namespace App\Services\Api;

use App\Constants\FieldType;
use App\Exceptions\BusinessException;
use App\Models\Api\Badge;
use App\Repositories\Api\BadgeRepository;
use App\Repositories\Api\CopyTradingRepository;
use Carbon\Carbon;

class BadgeService
{
    private const EVENT_PARAM_RENDER_MODE_SELECT = 1;

    private const EVENT_PARAM_RENDER_MODE_MULTI = 2;

    private const EVENT_PARAM_RELATION_OPTIONS = ['or', 'and'];

    private const EVENT_KEYS_SUPPORT_PARAM_RELATION = ['close_trade'];

    private ?array $eventOptionsCache = null;

    private array $eventParamDefinitionsCache = [];

    public function __construct(
        private BadgeRepository $repo,
        private CopyTradingRepository $copyTradingRepo,
    ) {}

    public function getList(string $keyword, ?int $status, int $limit, int $offset): array
    {
        [$records, $count] = $this->repo->getList($keyword, $status, $limit, $offset);

        $data = array_map(fn ($record) => $this->formatBadge($record), $records);

        return [
            'data' => $data,
            'total_count' => $count,
        ];
    }

    public function create(array $payload): array
    {
        $localeConfigs = $payload['locale_configs'] ?? [];
        $localized = $this->normalizeLocalizedFields($localeConfigs);
        $this->ensureUniqueName($localized['names']);
        $shortName = trim((string) ($payload['short_name'] ?? ''));
        $this->ensureUniqueShortName($shortName);

        $eventKey = (string) $payload['event_key'];
        $eventParams = $this->mergeRelationIntoEventParams(
            $eventKey,
            $this->normalizeEventParamsForStorage($eventKey, $payload['event_params'] ?? []),
            $payload['relation'] ?? null
        );

        $badge = $this->repo->create([
            'short_name' => $shortName,
            'name' => $localized['encoded']['name'],
            'description' => $localized['encoded']['description'],
            'icon' => $payload['icon'],
            'event_key' => $eventKey,
            'event_params' => $eventParams,
            'status' => Badge::STATUS_ENABLED,
        ]);

        return $this->formatBadge($badge);
    }

    public function update(int $id, array $payload): array|bool|null
    {
        $badge = $this->repo->findById($id);
        if (! $badge) {
            return null;
        }

        $shortName = trim((string) ($payload['short_name'] ?? ''));
        $this->ensureUniqueShortName($shortName, $id);

        $localeConfigs = $payload['locale_configs'] ?? [];
        $localized = $this->normalizeLocalizedFields($localeConfigs);
        $this->ensureUniqueName($localized['names'], $id);
        $payload['short_name'] = $shortName;
        $payload['name'] = $localized['encoded']['name'];
        $payload['description'] = $localized['encoded']['description'];
        unset($payload['locale_configs']);

        $storedEventKey = (string) $badge->event_key;
        $eventKey = (string) ($payload['event_key'] ?? $storedEventKey);
        $payload['event_key'] = $eventKey;
        $eventParamsInput = array_key_exists('event_params', $payload)
            ? $payload['event_params']
            : ($eventKey === $storedEventKey ? ($badge->event_params ?? []) : []);
        $payload['event_params'] = $this->mergeRelationIntoEventParams(
            $eventKey,
            $this->normalizeEventParamsForStorage($eventKey, $eventParamsInput),
            $payload['relation'] ?? null
        );
        unset($payload['relation']);

        if (! array_key_exists('icon', $payload)) {
            $payload['icon'] = $badge->icon;
        }

        $updated = $this->repo->updateById($id, $payload, $badge);

        return $updated ? $this->formatBadge($updated) : false;
    }

    public function changeStatus(int $id, int $status): ?bool
    {
        $badge = $this->repo->findById($id);
        if (! $badge) {
            return null;
        }

        if ($badge->status === $status) {
            return true;
        }

        $updated = $this->repo->updateById($id, ['status' => $status], $badge);

        return (bool) $updated;
    }

    public function getEventOptions(): array
    {
        if ($this->eventOptionsCache !== null) {
            return $this->eventOptionsCache;
        }

        $options = Badge::eventOptions();

        foreach ($options as &$option) {
            $eventKey = (string) ($option['key'] ?? '');
            $supportsParamRelation = in_array(
                $eventKey,
                self::EVENT_KEYS_SUPPORT_PARAM_RELATION,
                true
            );
            $option['relation'] = $supportsParamRelation
                ? self::EVENT_PARAM_RELATION_OPTIONS
                : [];
            $eventParams = is_array($option['event_params'] ?? null)
                ? $option['event_params']
                : [];
            $option['render_mode'] = ! $supportsParamRelation && count($eventParams) > 1
                ? self::EVENT_PARAM_RENDER_MODE_MULTI
                : self::EVENT_PARAM_RENDER_MODE_SELECT;

            if ($eventKey !== Badge::EVENT_KEY_MASTER_LEVEL_CHANGED) {
                continue;
            }

            $option['event_params'] = $this->buildMasterLevelChangedEventParams();
        }
        unset($option);

        $this->eventOptionsCache = $options;

        return $this->eventOptionsCache;
    }

    /**
     * @return array<int, array{type:string,param_key:string,param_name:string}>
     */
    private function buildMasterLevelChangedEventParams(): array
    {
        $levels = $this->copyTradingRepo->getEnabledMasterLevelParams();
        $params = [];

        foreach ($levels as $level) {
            $levelPos = (int) ($level['level_pos'] ?? 0);
            if ($levelPos <= 0) {
                continue;
            }

            $params[] = [
                'type' => (string) FieldType::NONE,
                'param_key' => (string) $levelPos,
                'param_name' => $this->resolveLocaleValue(
                    $this->decodeLocaleField($level['name'] ?? null)
                ),
            ];
        }

        return $params;
    }

    private function normalizeEventParamsForStorage(string $eventKey, array $params): array
    {
        if ($eventKey === Badge::EVENT_KEY_MASTER_LEVEL_CHANGED) {
            return $this->normalizeMasterLevelChangedEventParams($params);
        }

        return $this->collapseEventParams($this->normalizeEventParams($params));
    }

    private function normalizeMasterLevelChangedEventParams(array $params): array
    {
        if ($params === []) {
            return [];
        }

        if ($this->isAssocArray($params)) {
            $params = $this->looksLikeEventParamItem($params)
                ? [$params]
                : array_map(
                    static fn (int|string $key, mixed $value): array => [$key => $value],
                    array_keys($params),
                    array_values($params)
                );
        }

        $levelKeys = [];
        foreach ($params as $param) {
            if (is_array($param)) {
                $levelKey = trim($this->extractEventParamKey($param));
                if ($levelKey === 'level_pos') {
                    $levelKey = trim((string) $this->extractEventParamValue($param));
                }
            } elseif (is_int($param) || is_string($param)) {
                $levelKey = trim((string) $param);
            } else {
                continue;
            }

            if ($levelKey !== '' && ! in_array($levelKey, $levelKeys, true)) {
                $levelKeys[] = $levelKey;
            }
        }

        return $levelKeys;
    }

    private function normalizeLocalizedFields(array $localeConfigs): array
    {
        $languages = [];
        $names = [];
        $descriptions = [];

        foreach ($localeConfigs as $config) {
            if (! is_array($config)) {
                continue;
            }

            $lang = $config['lang'] ?? null;
            if ($lang === null || $lang === '') {
                continue;
            }

            $languages[] = $lang;
            $names[$lang] = isset($config['name']) ? trim((string) $config['name']) : '';
            $descriptions[$lang] = isset($config['description']) ? trim((string) $config['description']) : '';
        }

        $languages = array_values(array_filter($languages, fn ($value) => $value !== ''));
        if (count($languages) !== count(array_unique($languages))) {
            throw new BusinessException(trans('badge.message.duplicate_lang'));
        }

        $names = array_filter($names, fn ($value) => $value !== '');
        if (empty($names)) {
            throw new BusinessException(trans('badge.message.name_required'));
        }

        $descriptions = array_filter($descriptions, fn ($value) => $value !== '');

        return [
            'encoded' => [
                'name' => $names,
                'description' => $descriptions,
            ],
            'names' => array_values($names),
        ];
    }

    private function ensureUniqueName(array $names, ?int $excludeId = null): void
    {
        if ($this->repo->existsByName($names, $excludeId)) {
            throw new BusinessException(trans('badge.message.duplicate_name'));
        }
    }

    private function ensureUniqueShortName(string $shortName, ?int $excludeId = null): void
    {
        if ($this->repo->existsByShortName($shortName, $excludeId)) {
            throw new BusinessException(trans('badge.message.duplicate_short_name'));
        }
    }

    private function normalizeEventParams(array $params): array
    {
        if (empty($params)) {
            return [];
        }

        // Map input like {start_at:xxx,end_at:xxx}
        if ($this->isAssocArray($params) && ! $this->looksLikeEventParamItem($params)) {
            return $this->mapToEventParamList($params);
        }

        // Single item should still be treated as a list
        if ($this->isAssocArray($params)) {
            $params = [$params];
        }

        $result = [];
        foreach ($params as $item) {
            if (! is_array($item)) {
                continue;
            }
            foreach ($this->normalizeEventParamItem($item) as $normalized) {
                if (! empty($normalized)) {
                    $result[] = $normalized;
                }
            }
        }

        return $result;
    }

    /**
     * Collapse normalized params into a single key-value map for storage.
     * Falls back to the original list when data looks unexpected to avoid breaking input handling.
     */
    private function collapseEventParams(array $params): array
    {
        if (empty($params)) {
            return [];
        }

        $collapsed = [];
        foreach ($params as $item) {
            if (! is_array($item) || ! $this->isAssocArray($item)) {
                return $params;
            }

            $variable = $this->extractEventParamKey($item);
            if ($variable === '' || array_key_exists($variable, $collapsed)) {
                return $params;
            }

            $collapsed[$variable] = $this->normalizeStoredEventParamValue(
                $this->extractEventParamValue($item)
            );
        }

        return $collapsed;
    }

    private function normalizeStoredEventParamValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return $value;
        }

        return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
    }

    /**
     * 支持 param_key/param_value、variable/value、或单个键值对等多种输入形式
     */
    private function normalizeEventParamItem(array $params): array
    {
        if (array_key_exists('param_key', $params) || array_key_exists('variable', $params)) {
            $variable = (string) ($params['param_key'] ?? $params['variable'] ?? '');
            $variable = trim($variable);
            if ($variable === '') {
                return [];
            }

            $value = $params['param_value'] ?? ($params['value'] ?? '');

            return [[$variable => $value]];
        }

        if (! $this->isAssocArray($params)) {
            return [];
        }

        // 如果形如 {start_at:xxx,end_at:xxx}
        return $this->mapToEventParamList($params);
    }

    private function mapToEventParamList(array $params): array
    {
        $ignoredKeys = ['type', 'param_name', 'description'];
        $result = [];
        foreach ($params as $key => $value) {
            $variable = trim((string) $key);
            if ($variable === '' || in_array($variable, $ignoredKeys, true)) {
                continue;
            }
            $result[] = [$variable => $value];
        }

        return $result;
    }

    private function isAssocArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private function looksLikeEventParamItem(array $params): bool
    {
        return array_key_exists('param_key', $params)
            || array_key_exists('param_value', $params)
            || array_key_exists('variable', $params)
            || array_key_exists('value', $params)
            || array_key_exists('type', $params);
    }

    private function extractEventParamValue(array $param): mixed
    {
        if (array_key_exists('param_value', $param)) {
            return $param['param_value'];
        }
        if (array_key_exists('value', $param)) {
            return $param['value'];
        }
        if ($this->isAssocArray($param)) {
            $variable = $this->extractEventParamKey($param);
            if ($variable !== '' && array_key_exists($variable, $param)) {
                return $param[$variable];
            }
            if (count($param) === 1) {
                return current($param);
            }
        }

        return null;
    }

    private function extractEventParamKey(array $param): string
    {
        if (array_key_exists('param_key', $param)) {
            return (string) $param['param_key'];
        }
        if (array_key_exists('variable', $param)) {
            return (string) $param['variable'];
        }

        if ($this->isAssocArray($param)) {
            if (count($param) === 1) {
                return (string) array_key_first($param);
            }

            $metaKeys = ['description', 'type', 'param_name', 'param_value', 'value'];
            foreach (array_keys($param) as $key) {
                if (! in_array($key, $metaKeys, true)) {
                    return (string) $key;
                }
            }
        }

        return '';
    }

    private function supportsParamRelation(string $eventKey): bool
    {
        return in_array($eventKey, self::EVENT_KEYS_SUPPORT_PARAM_RELATION, true);
    }

    private function mergeRelationIntoEventParams(
        string $eventKey,
        array $eventParams,
        mixed $relation
    ): array {
        $storedRelation = $this->extractRelationFromEventParams($eventKey, $eventParams);
        unset($eventParams['relation']);

        if (! $this->supportsParamRelation($eventKey)) {
            return $eventParams;
        }

        $relation = $relation ?? $storedRelation;
        if (is_string($relation) && in_array($relation, self::EVENT_PARAM_RELATION_OPTIONS, true)) {
            $eventParams['relation'] = $relation;
        }

        return $eventParams;
    }

    private function extractRelationFromEventParams(string $eventKey, array $eventParams): ?string
    {
        if (! $this->supportsParamRelation($eventKey)) {
            return null;
        }

        $relation = $eventParams['relation'] ?? null;
        if (! is_string($relation) || ! in_array($relation, self::EVENT_PARAM_RELATION_OPTIONS, true)) {
            return null;
        }

        return $relation;
    }

    private function stripRelationFromEventParams(array $eventParams): array
    {
        unset($eventParams['relation']);

        return $eventParams;
    }

    private function formatBadge(Badge|array $badge): array
    {
        if ($badge instanceof Badge) {
            $badge = $badge->toArray();
        }

        $nameTranslations = $this->decodeLocaleField($badge['name'] ?? null);
        $descriptionTranslations = $this->decodeLocaleField($badge['description'] ?? null);

        $eventParams = $badge['event_params'] ?? [];
        if (! is_array($eventParams)) {
            $decoded = json_decode((string) $eventParams, true);
            $eventParams = json_last_error() === JSON_ERROR_NONE ? ($decoded ?: []) : [];
        }
        $eventKey = (string) ($badge['event_key'] ?? '');
        $relation = $this->extractRelationFromEventParams($eventKey, $eventParams);
        $eventParams = $this->normalizeEventParamsForStorage($eventKey, $eventParams);
        $relation ??= $this->extractRelationFromEventParams($eventKey, $eventParams);
        $eventParams = $this->stripRelationFromEventParams($eventParams);
        $eventParamTextMap = $this->buildEventParamTextMap($eventKey, $eventParams);
        $eventParamTexts = array_values($eventParamTextMap);
        $eventText = $this->buildEventText($eventParamTextMap, $relation);
        $eventParams = $this->formatEventParamsForResponse($eventKey, $eventParams);

        return [
            'id' => (int) ($badge['id'] ?? 0),
            'icon' => $badge['icon'] ?? '',
            'short_name' => $badge['short_name'] ?? '',
            'event_key' => $eventKey,
            'event_key_text' => Badge::eventLabel($eventKey),
            'event_params' => $eventParams,
            'event_params_text' => $eventParamTexts,
            'event_text' => $eventText,
            'relation' => $relation,
            'status' => (int) ($badge['status'] ?? Badge::STATUS_ENABLED),
            'status_text' => Badge::statusLabel((int) ($badge['status'] ?? Badge::STATUS_ENABLED)),
            'locale_configs' => $this->buildLocaleConfigs($nameTranslations, $descriptionTranslations),
            'created_at' => $this->formatDateTime($badge['created_at'] ?? null),
            'updated_at' => $this->formatDateTime($badge['updated_at'] ?? null),
        ];
    }

    private function formatEventParamsForResponse(string $eventKey, array $eventParams): array
    {
        if ($eventKey !== Badge::EVENT_KEY_MASTER_LEVEL_CHANGED) {
            return $eventParams;
        }

        return array_fill_keys($eventParams, '');
    }

    /**
     * @return array<string, string>
     */
    private function buildEventParamTextMap(string $eventKey, array $eventParams): array
    {
        if ($eventKey === '' || $eventParams === []) {
            return [];
        }

        $definitions = $this->resolveEventParamDefinitions($eventKey);
        if ($definitions === []) {
            return [];
        }

        if ($eventKey === Badge::EVENT_KEY_MASTER_LEVEL_CHANGED) {
            $texts = [];
            foreach ($eventParams as $paramKey) {
                $definition = $definitions[(string) $paramKey] ?? null;
                $paramName = trim((string) ($definition['param_name'] ?? ''));
                if ($paramName !== '') {
                    $texts[(string) $paramKey] = $paramName;
                }
            }

            return $texts;
        }

        $texts = [];
        foreach ($eventParams as $paramKey => $value) {
            $definition = $definitions[(string) $paramKey] ?? null;
            if (! is_array($definition)) {
                continue;
            }

            $text = $this->formatEventParamText($definition, $value);
            if ($text !== null) {
                $texts[(string) $paramKey] = $text;
            }
        }

        return $texts;
    }

    private function buildEventText(array $eventParamTextMap, ?string $relation): string
    {
        if ($eventParamTextMap === []) {
            return '';
        }

        $tradeAccountTypeText = $eventParamTextMap['trade_account_type'] ?? null;
        unset($eventParamTextMap['trade_account_type']);

        $conditionText = $relation === null || $relation === ''
            ? implode('，', $eventParamTextMap)
            : implode(sprintf(' %s ', $relation), $eventParamTextMap);

        if ($tradeAccountTypeText === null) {
            return $conditionText;
        }

        return $conditionText === ''
            ? $tradeAccountTypeText
            : sprintf('%s 、 %s', $conditionText, $tradeAccountTypeText);
    }

    /**
     * @return array<string, array{param_key:string,param_name:string,type:int,options:array<int, array{value:mixed,label:string}>}>
     */
    private function resolveEventParamDefinitions(string $eventKey): array
    {
        if (array_key_exists($eventKey, $this->eventParamDefinitionsCache)) {
            return $this->eventParamDefinitionsCache[$eventKey];
        }

        $params = [];
        if ($eventKey === Badge::EVENT_KEY_MASTER_LEVEL_CHANGED) {
            $params = $this->buildMasterLevelChangedEventParams();
        } else {
            foreach (Badge::eventOptions() as $option) {
                if (($option['key'] ?? '') === $eventKey) {
                    $params = is_array($option['event_params'] ?? null)
                        ? $option['event_params']
                        : [];
                    break;
                }
            }
        }

        $definitions = [];
        foreach ($params as $param) {
            if (! is_array($param)) {
                continue;
            }

            $paramKey = trim((string) ($param['param_key'] ?? ''));
            if ($paramKey === '') {
                continue;
            }

            $definitions[$paramKey] = [
                'param_key' => $paramKey,
                'param_name' => (string) ($param['param_name'] ?? ''),
                'type' => (int) ($param['type'] ?? 0),
                'options' => is_array($param['options'] ?? null) ? $param['options'] : [],
            ];
        }

        return $this->eventParamDefinitionsCache[$eventKey] = $definitions;
    }

    private function formatEventParamText(array $definition, mixed $value): ?string
    {
        $label = $this->stripLeadingComparator((string) ($definition['param_name'] ?? ''));
        $displayValue = $this->resolveEventParamDisplayValue($definition, $value);
        if ($label === '' || $displayValue === null || $displayValue === '') {
            return null;
        }

        if ((int) ($definition['type'] ?? 0) === FieldType::SELECT) {
            return sprintf('%s:%s', $label, $displayValue);
        }

        return sprintf('%s >= %s', $label, $displayValue);
    }

    private function resolveEventParamDisplayValue(array $definition, mixed $value): mixed
    {
        foreach ($definition['options'] ?? [] as $option) {
            if (! is_array($option) || ! array_key_exists('value', $option)) {
                continue;
            }

            if ((string) $option['value'] === (string) $value) {
                return $option['label'] ?? $value;
            }
        }

        return is_array($value)
            ? ($value['value'] ?? $value['amount'] ?? $value['days'] ?? null)
            : $value;
    }

    private function stripLeadingComparator(string $label): string
    {
        $label = preg_replace('/^(?:[≥>]=?|=)\s*/u', '', $label);

        return trim((string) $label);
    }

    private function decodeLocaleField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $fallback = config('app.fallback_locale', 'en_US');

        return [$fallback => (string) $value];
    }

    private function resolveLocaleValue(array $translations): string
    {
        if (empty($translations)) {
            return '';
        }

        $currentLocale = app()->getLocale() ?: config('app.locale');
        if ($currentLocale && isset($translations[$currentLocale]) && $translations[$currentLocale] !== '') {
            return (string) $translations[$currentLocale];
        }

        $fallback = config('app.fallback_locale', 'en_US');
        if (isset($translations[$fallback]) && $translations[$fallback] !== '') {
            return (string) $translations[$fallback];
        }

        $first = reset($translations);

        return is_string($first) ? $first : '';
    }

    private function buildLocaleConfigs(array $names, array $descriptions): array
    {
        $languages = array_unique(array_merge(array_keys($names), array_keys($descriptions)));
        if (empty($languages)) {
            return [];
        }

        $ordered = $this->orderLanguages($languages);
        $result = [];

        foreach ($ordered as $lang) {
            $result[] = [
                'lang' => $lang,
                'name' => $names[$lang] ?? '',
                'description' => $descriptions[$lang] ?? '',
            ];
        }

        return $result;
    }

    private function orderLanguages(array $languages): array
    {
        $configured = array_keys(config('lang.languages', []));
        $ordered = [];

        foreach ($configured as $lang) {
            if (in_array($lang, $languages, true)) {
                $ordered[] = $lang;
            }
        }

        foreach ($languages as $lang) {
            if (! in_array($lang, $ordered, true)) {
                $ordered[] = $lang;
            }
        }

        return $ordered;
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('Y-m-d H:i:s');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}
