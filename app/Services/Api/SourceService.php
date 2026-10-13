<?php

namespace App\Services\Api;

use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\SourceLeverageRepository;
use App\Repositories\Api\SourceRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use Scaffold\Framework\Models\Api\TsSource;

class SourceService
{
    public function __construct(
        private SourceRepository $sourceRepo,
        private SourceLeverageRepository $sourceLeverageRepo,
        private UserOperationLogRepository $userOperationLogRepo
    ) {
    }

    /**
     * 品种列表
     */
    public function getList(
        string $sourceCode,
        string $nameKeyword,
        string $sourceType,
        ?int $status,
        int $limit,
        int $offset
    ): array {
        [$records, $count] = $this->sourceRepo->getList($sourceCode, $nameKeyword, $sourceType, $status, $limit, $offset);
        $sources           = array_column($records, 'source');
        $leverages         = $this->sourceLeverageRepo->getBySources($sources);
        $typeValueMap      = $this->buildTypeValueMap();

        $data = array_map(function (array $record) use ($leverages, $typeValueMap) {
            $source = $record['source'] ?? '';

            $formatted = $this->formatSource($record, $leverages[$source] ?? []);
            if (isset($formatted['source_type'])) {
                $formatted['source_type'] = $typeValueMap[strtolower((string) $formatted['source_type'])] ?? $formatted['source_type'];
            }
            unset($formatted['name'], $formatted['config'], $formatted['leverages']);

            return $formatted;
        }, $records);

        return [
            'data'        => $data,
            'total_count' => $count,
        ];
    }

    /**
     * 获取全部品种代码。
     *
     * @return array<int, string>
     */
    public function getAllSources(): array
    {
        return $this->sourceRepo->getAllSources();
    }

    /**
     * 获取品种小数位
     */
    public function getSourceInfos(){
        $sources = $this->sourceRepo->getAll();

        $sourceDigits = [];
        $sourceContractSize = [];
        foreach ($sources as $source) {
            $sourceDigits[$source['source']] = $source['config']['digits'];
            $sourceContractSize[$source['source']] = $source['config']['contract_size'];
        }
        return [$sourceDigits, $sourceContractSize];
    }

    /**
     * 获取品种分类映射（source => category）
     *
     * @return array<string, string>
     */
    public function getSourceCategories(): array
    {
        $sources = $this->sourceRepo->getAll();
        $categories = [];
        foreach ($sources as $source) {
            $code = (string) ($source['source'] ?? '');
            if ($code === '') {
                continue;
            }

            $categories[$code] = (string) ($source['source_type'] ?? '');
        }

        return $categories;
    }

    /**
     * 更新品种信息
     */
    public function updateSource(int $id, array $payload): array
    {
        $source = $this->sourceRepo->findById($id);
        if (! $source) {
            throw new \Exception(trans('public.message.data_error'));
        }

        $localized = $this->normalizeLocaleConfigs($payload['locale_configs'] ?? []);
        if (empty($localized['names'])) {
            throw new \Exception(trans('public.message.default_lang'));
        }

        $updateData = [
            'name'        => $localized['names'],
            'icon'        => $payload['icon'],
            'category' => $payload['source_type'],
        ];

        $this->sourceRepo->updateById($id, $updateData);
        $sourceCode = $source->source ?? (string) $id;
        $this->logOperation($id, $sourceCode, $localized['names'], $payload['source_type']);

        $fresh     = $this->sourceRepo->findById($id);
        $leverages = $sourceCode ? $this->sourceLeverageRepo->getBySources([$sourceCode]) : [];
        $data      = $fresh?->toArray() ?? array_merge(['source' => $sourceCode], $updateData);

        return $this->formatSource($data, $leverages[$sourceCode] ?? []);
    }

    /**
     * 获取分类选项
     */
    public function getTypeOptions(): array
    {
        $types = [
            TsSource::FOREX,
            TsSource::METAL,
            TsSource::ENERGY,
            TsSource::INDEX,
            TsSource::CRYPTO,
            TsSource::STOCK,
        ];

        return array_map(
            fn (string $type) => ['key' => $type, 'value' => $type],
            $types
        );
    }

    private function buildTypeValueMap(): array
    {
        $options = $this->getTypeOptions();
        $map     = [];

        foreach ($options as $option) {
            $key         = strtolower((string) ($option['key'] ?? ''));
            $value       = (string) ($option['value'] ?? '');
            $map[$key]   = $value;
        }

        return $map;
    }

    private function formatSource(array $record, array $leverages): array
    {
        $names                    = $this->decodeMultilingualValue($record['name'] ?? null);
        $record['display_name']   = $this->resolveLocaleDisplayValue($names);
        $record['locale_configs'] = $this->buildLocaleConfigs($names);
        $record['leverages']      = array_values($leverages);
        $record['status_name']    = $this->resolveStatusName($record['status'] ?? null);

        return $record;
    }

    private function decodeMultilingualValue(mixed $value): array
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

    private function resolveLocaleDisplayValue(array $values): string
    {
        if (empty($values)) {
            return '';
        }

        $currentLocale = app()->getLocale() ?: config('app.locale');
        if ($currentLocale && isset($values[$currentLocale]) && $values[$currentLocale] !== '') {
            return (string) $values[$currentLocale];
        }

        $fallback = config('app.fallback_locale', 'en_US');
        if (isset($values[$fallback]) && $values[$fallback] !== '') {
            return (string) $values[$fallback];
        }

        $first = reset($values);

        return is_string($first) ? $first : '';
    }

    private function resolveStatusName(mixed $status): string
    {
        // Translate status into a localized label, falling back to "unknown" when undefined.
        $statusKey   = $status === null || $status === '' ? 'unknown' : (string) $status;
        $lookupKey   = "source.status.$statusKey";
        $translated  = trans($lookupKey);

        return $translated === $lookupKey ? trans('source.status.unknown') : $translated;
    }

    private function buildLocaleConfigs(array $names): array
    {
        if (empty($names)) {
            return [];
        }

        $langs   = array_keys(config('lang.languages', []));
        $ordered = [];
        foreach ($langs as $lang) {
            if (isset($names[$lang])) {
                $ordered[$lang] = $names[$lang];
                unset($names[$lang]);
            }
        }
        $ordered = $ordered + $names;

        $result = [];
        foreach ($ordered as $lang => $value) {
            $result[] = [
                'lang' => $lang,
                'name' => (string) $value,
            ];
        }

        return $result;
    }

    private function normalizeLocaleConfigs(array $localeConfigs): array
    {
        $languages = [];
        foreach ($localeConfigs as $config) {
            if (! is_array($config)) {
                continue;
            }
            $lang = $config['lang'] ?? null;
            if ($lang === null || $lang === '') {
                continue;
            }
            $name = trim((string) ($config['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $languages[$lang] = $name;
        }

        return [
            'names' => $languages,
        ];
    }

    private function logOperation(int $sourceId, string $source, array $names, string $sourceType): void
    {
        $content = json_encode([
            'source'      => $source,
            'names'       => $names,
            'source_type' => $sourceType,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->userOperationLogRepo->addRecord(
            $sourceId,
            UserOperationLog::TARGET_TYPE_SOURCE,
            $content,
            UserOperationLog::ACTION_TYPE_UPDATE
        );
    }
}
