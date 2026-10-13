<?php

namespace App\Services\Api;

use App\Repositories\Api\AppReleaseRepository;

class AppReleaseService
{
    public function __construct(private AppReleaseRepository $repo)
    {
    }

    /**
     * 获取各平台最新版本列表。
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $rows = $this->repo->listLatestByPlatform();

        return array_map(fn (array $row) => $this->formatListRow($row), $rows);
    }

    public function create(array $payload): array
    {
        $platform = (string) $payload['platform'];
        $version = (string) $payload['version'];
        $buildNumber = (int) $payload['build_number'];

        if ($this->repo->existsUnique($platform, $version, $buildNumber)) {
            return [
                'ok' => false,
                'message' => trans('public.message.already_exist'),
            ];
        }

        $id = $this->repo->createRelease([
            'platform' => $platform,
            'version' => $version,
            'build_number' => $buildNumber,
            'title' => $this->localeConfigsToMap($payload['locale_configs'] ?? [], 'title'),
            'description' => $this->localeConfigsToMap($payload['locale_configs'] ?? [], 'description'),
            'is_forced_update' => (int) $payload['is_forced_update'],
            'download_url' => $payload['download_url'] ?? null,
        ]);

        return [
            'ok' => true,
            'data' => ['id' => $id],
        ];
    }

    public function update(int $id, array $payload): ?array
    {
        $existing = $this->repo->findById($id);
        if (! $existing) {
            return null;
        }

        $updated = $this->repo->updateRelease($existing, [
            'title' => $this->localeConfigsToMap($payload['locale_configs'] ?? [], 'title'),
            'description' => $this->localeConfigsToMap($payload['locale_configs'] ?? [], 'description'),
            'is_forced_update' => (int) $payload['is_forced_update'],
            'download_url' => $payload['download_url'] ?? null,
        ]);

        return ['updated' => $updated];
    }

    private function formatListRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'platform' => (string) ($row['platform'] ?? ''),
            'version' => (string) ($row['version'] ?? ''),
            'build_number' => (int) ($row['build_number'] ?? 0),
            'locale_configs' => $this->mergeLocaleConfigs($row['title'] ?? [], $row['description'] ?? []),
            'is_forced_update' => (int) ($row['is_forced_update'] ?? 0),
            'download_url' => $row['download_url'] ?? null,
            'created_at' => isset($row['created_at']) && $row['created_at'] !== null ? date('Y-m-d H:i:s', strtotime((string) $row['created_at'])) : null,
            'updated_at' => isset($row['updated_at']) && $row['updated_at'] !== null ? date('Y-m-d H:i:s', strtotime((string) $row['updated_at'])) : null,
        ];
    }

    private function localeConfigsToMap(array $localeConfigs, string $field): array
    {
        $map = [];
        foreach ($localeConfigs as $config) {
            if (! is_array($config)) {
                continue;
            }
            $lang = trim((string) ($config['lang'] ?? ''));
            $value = trim((string) ($config[$field] ?? ''));
            if ($lang === '' || $value === '') {
                continue;
            }
            $map[$lang] = $value;
        }

        return $map;
    }

    private function mergeLocaleConfigs(array|string|null $titleMap, array|string|null $descriptionMap): array
    {
        $titleMap = is_array($titleMap) ? $titleMap : [];
        $descriptionMap = is_array($descriptionMap) ? $descriptionMap : [];
        $langs = array_values(array_unique(array_merge(array_keys($titleMap), array_keys($descriptionMap))));

        $configs = [];
        foreach ($langs as $lang) {
            $configs[] = [
                'lang' => (string) $lang,
                'title' => (string) ($titleMap[$lang] ?? ''),
                'description' => (string) ($descriptionMap[$lang] ?? ''),
            ];
        }

        return $configs;
    }
}
