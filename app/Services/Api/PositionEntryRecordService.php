<?php

namespace App\Services\Api;

use App\Models\Api\AppRelease;
use App\Models\Api\PositionRecord;
use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\PositionEntryRepository;
use App\Repositories\Api\PositionRecordRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use Carbon\Carbon;

class PositionEntryRecordService
{
    public function __construct(
        private PositionEntryRepository     $entryRepo,
        private PositionRecordRepository    $recordRepo,
        private AdminUserRepository         $adminUserRepo,
        private UserOperationLogRepository $userOperationLogRepo,
        private PositionRecordStatusService $statusService,
    ) {
    }

    /**
     * 广告列表
     *
     * @param array $filters 过滤条件
     *
     * @return array
     */
    public function eList(array $filters = [])
    {
        $records = $this->recordRepo->listByFilters($filters);
        $operatorNames = $this->resolveOperatorNames(array_column($records, 'id'));
        $records = array_map(function ($record) use ($operatorNames) {
            $record['operate_user_name'] = $operatorNames[$record['id']] ?? '';

            $formatted = $this->formatRecord($record);

            return $formatted;
        }, $records);

        return [
            'identifier' => (string) ($filters['identifier'] ?? ''),
            'total_count' => count($records),
            'data' => $records,
        ];
    }

    /**
     * 删除广告
     *
     * @param int $recId 记录id
     *
     * @return int
     */
    public function delRecord($recId)
    {
        $deleted = $this->recordRepo->delete($recId, 'id');
        if ($deleted) {
            $this->logRecordOperation((int) $recId, 'delete', UserOperationLog::ACTION_TYPE_DELETE);
        }

        return $deleted;
    }

    /**
     * 添加广告位下的广告
     *
     * @param string $identifier  广告位标识
     * @param array  $platforms   平台
     * @param string $name        广告名称（多语言JSON）
     * @param string $description 描述（多语言JSON）
     * @param string $imgUrl      图片URL（多语言JSON）
     * @param string $link        跳转（多语言JSON，数字=内部模块，URL=外链）
     * @param string $startTime   开启时间
     * @param string $endTime     结束时间
     *
     * @return bool
     */
    public function add(
        $identifier,
        array $platforms,
        $name,
        $description,
        $imgUrl,
        $link,
        $startTime,
        $endTime,
        ?int $status = null
    ) {
        $identifier = $this->normalizeIdentifierValue($identifier);
        $platforms = $this->normalizePlatforms($platforms);
        $platformValue = $this->encodePlatformValue($platforms);
        if ($identifier === '' || $platformValue === '') {
            return false;
        }

        if (! $this->entryRepo->firstByIdentifier($identifier)) {
            return false;
        }

        $link = $link === '' ? null : $link;
        $status = $this->statusService->resolveStatus(
            $status ?? PositionRecord::STATUS_ENABLED,
            $startTime,
            $endTime,
        );

        $recordId = $this->recordRepo->insertGetId(
            [
                'identifier' => $identifier,
                'platform' => $platformValue,
                'name' => $name,
                'description' => $description,
                'image' => $imgUrl,
                'link' => $link,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $status,
            ]
        );

        if (! $recordId) {
            return false;
        }

        $this->logRecordOperation((int) $recordId, 'create');

        return true;
    }

    /**
     * 获取广告位下的广告列表
     *
     * @param string $identifier 广告位标识
     *
     * @return array
     */
    public function entryRecordList(
        $identifier,
        ?string $platform = null,
        ?int $excludeId = null,
        int|array|null $status = null
    )
    {
        $identifier = $this->normalizeIdentifierValue($identifier);
        if ($identifier === '') {
            return [];
        }

        return $this->recordRepo->listByIdentifierPlatform($identifier, $platform, $excludeId, $status);
    }

    /**
     * 获取广告信息
     *
     * @param int $recId 记录ID
     *
     * @return object
     */
    public function recordInfoById($recId)
    {
        return $this->recordRepo->first($recId);
    }

    /**
     * 修改条目内广告
     *
     * @param int    $recId       记录id
     * @param string $name        广告名称（多语言JSON）
     * @param string $description 描述（多语言JSON）
     * @param string $imgUrl      图片URL（多语言JSON）
     * @param string $link        跳转（多语言JSON，数字=内部模块，URL=外链）
     * @param string $startTime   开启时间
     * @param string $endTime     结束时间
     *
     * @return bool
     */
    public function update(
        $recId,
        $identifier,
        $platform,
        $name,
        $description,
        $imgUrl,
        $link,
        $startTime,
        $endTime,
        ?int $status = null
    ) {
        $identifier = $this->normalizeIdentifierValue($identifier);
        if ($identifier === '') {
            return false;
        }

        if (! $this->entryRepo->firstByIdentifier($identifier)) {
            return false;
        }

        $platformValue = $this->encodePlatformValue($platform);
        if ($platformValue === '') {
            return false;
        }

        $link = $link === '' ? null : $link;
        $update = [
            'identifier' => $identifier,
            'platform' => $platformValue,
            'name' => $name,
            'description' => $description,
            'image' => $imgUrl,
            'link' => $link,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
        if ($status !== null) {
            $update['status'] = $this->statusService->resolveStatus(
                $status,
                $startTime,
                $endTime,
            );
        }
        $result = $this->recordRepo->update(
            $update,
            $recId
        );

        if ($result) {
            $this->logRecordOperation((int) $recId, 'update', UserOperationLog::ACTION_TYPE_UPDATE);
        }

        return $result;
    }

    /**
     * 按平台过滤广告
     *
     * @param array $records        原始记录
     * @param array $platformFilter 平台过滤
     *
     * @return array
     */
    private function filterByPlatform(array $records, array $platformFilter): array
    {
        $platformFilter = array_values(array_unique(array_filter($platformFilter, function ($item) {
            return $item !== null && $item !== '';
        })));

        if (empty($platformFilter) || empty($records)) {
            return array_values($records);
        }

        return array_values(array_filter($records, function ($record) use ($platformFilter) {
            $recordPlatforms = $this->decodePlatforms($record['platform'] ?? null);
            if (empty($recordPlatforms)) {
                return false;
            }

            return (bool) array_intersect($recordPlatforms, $platformFilter);
        }));
    }

    /**
     * 按状态过滤
     *
     * @param array    $records      记录
     * @param int|null $statusFilter 状态
     *
     * @return array
     */
    private function filterByStatus(array $records, ?int $statusFilter): array
    {
        if ($statusFilter === null) {
            return $records;
        }

        return array_values(array_filter($records, function ($record) use ($statusFilter) {
            return (int) ($record['status'] ?? -1) === $statusFilter;
        }));
    }

    /**
     * 格式化广告记录
     *
     * @param array $record 原始记录
     *
     * @return array
     */
    private function formatRecord(array $record): array
    {
        $nameTranslations        = $this->decodeMultilingualValue($record['name'] ?? null);
        $descriptionTranslations = $this->decodeMultilingualValue($record['description'] ?? null);

        $record['name']        = $this->resolveLocaleDisplayValue($nameTranslations);
        $record['description'] = $this->resolveLocaleDisplayValue($descriptionTranslations);
        $record['identifier']  = (string) ($record['identifier'] ?? '');
        $platforms             = $this->decodePlatforms($record['platform'] ?? null);
        $imgLocales            = $this->decodePlatformLocalizedValue($record['image'] ?? null, $platforms);
        unset($record['image']);
        $linkLocales           = $this->decodePlatformLocalizedValue($record['link'] ?? ($record['target'] ?? null), $platforms);
        $targetLocales         = $this->decodePlatformLocalizedValue($record['target'] ?? null, $platforms);
        $targetParamLocales    = $this->decodePlatformLocalizedValue($record['target_param'] ?? null, $platforms);
        $targetTypeLocales     = $this->decodePlatformLocalizedValue($record['target_type'] ?? null, $platforms);
        unset($record['link'], $record['target'], $record['target_param'], $record['target_type']);
        $record['platform']    = $platforms;
        $record['status']      = isset($record['status']) ? (int) $record['status'] : 0;
        $record['status_name'] = trans('ad.status.' . $record['status']);
        $record['expired']     = $this->isExpired($record['end_time'] ?? null);
        $record['operate_user_name'] = $record['operate_user_name'] ?? '';
        $targetTypeNames = $this->buildLocalizedTargetTypeNames($targetTypeLocales, $linkLocales);
        $record['locale_configs'] = $this->buildLocaleConfigs([
            'img_url'      => $imgLocales,
            'link'         => $linkLocales,
            'target_url'   => $targetLocales,
            'target_param' => $targetParamLocales,
            'target_type'  => $targetTypeLocales,
        ], $targetTypeNames, $platforms);

        return $record;
    }

    /**
     * 规范广告位标识
     *
     * @param mixed $value 值
     *
     * @return string
     */
    private function normalizeIdentifierValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * 规范平台
     *
     * @param mixed $value 值
     *
     * @return array
     */
    private function normalizePlatforms(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map(function ($item) {
                return is_scalar($item) ? trim((string) $item) : '';
            }, $value), function ($item) {
                return $item !== '';
            })));
        }

        if (is_object($value)) {
            return $this->normalizePlatforms((array) $value);
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode(trim($value), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->normalizePlatforms($decoded);
            }

            $trimmed = trim($value, "[] \t\n\r\0\x0B");

            return array_values(array_filter(array_map('trim', explode(',', $trimmed)), function ($item) {
                return $item !== '';
            }));
        }

        if (is_scalar($value) && $value !== '') {
            return [trim((string) $value)];
        }

        return [];
    }

    private function encodePlatformValue(mixed $value): string
    {
        return implode(',', $this->normalizePlatforms($value));
    }

    /**
     * 解析多语言内容
     *
     * @param mixed $value 值
     *
     * @return array
     */
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

    /**
     * 解析平台维度下的多语言字段，兼容旧的一层 lang=>value 格式。
     *
     * @param array<int, string> $platforms 平台列表
     *
     * @return array<string, array<string, mixed>>
     */
    private function decodePlatformLocalizedValue(mixed $value, array $platforms): array
    {
        $decoded = $this->decodeMultilingualValue($value);
        if (empty($decoded)) {
            return [];
        }

        $supportedPlatforms = AppRelease::supportedPlatforms();
        $isPlatformMap = false;
        foreach ($decoded as $key => $item) {
            if (in_array((string) $key, $supportedPlatforms, true) && is_array($item)) {
                $isPlatformMap = true;
                break;
            }
        }

        if ($isPlatformMap) {
            $result = [];
            foreach ($decoded as $platform => $locales) {
                if (! is_array($locales)) {
                    continue;
                }
                $result[(string) $platform] = $this->normalizeLocaleMap($locales);
            }

            return $result;
        }

        $result = [];
        foreach ($platforms as $platform) {
            $result[$platform] = $this->normalizeLocaleMap($decoded);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $locales
     * @return array<string, mixed>
     */
    private function normalizeLocaleMap(array $locales): array
    {
        $result = [];
        foreach ($locales as $lang => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $result[(string) $lang] = $value;
        }

        return $result;
    }

    /**
     * 解析平台
     *
     * @param mixed $value 平台值
     *
     * @return array
     */
    private function decodePlatforms(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, function ($item) {
                return $item !== null && $item !== '';
            }));
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter($decoded, function ($item) {
                    return $item !== null && $item !== '';
                }));
            }

            return array_values(array_filter(array_map('trim', explode(',', $value)), function ($item) {
                return $item !== '';
            }));
        }

        return [];
    }

    /**
     * 解析多语言字段的展示值
     *
     * @param array $values 多语言内容
     *
     * @return string
     */
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
        return is_scalar($first) ? (string) $first : '';
    }

    /**
     * 拼装 locale_configs
     *
     * @param array $localizedFields 多语言字段集合
     * @param array $targetTypeNames 多语言 target_type 文案
     *
     * @return array
     */
    private function buildLocaleConfigs(array $localizedFields, array $targetTypeNames = [], array $platforms = []): array
    {
        $platformMap = [];
        foreach ($localizedFields as $fieldValues) {
            foreach ($fieldValues as $platform => $localizedValues) {
                if (! is_array($localizedValues) || empty($localizedValues)) {
                    continue;
                }
                $platformMap[(string) $platform] = true;
            }
        }

        if (empty($platformMap)) {
            return [];
        }

        $orderedPlatforms = [];
        foreach ($platforms as $platform) {
            if (isset($platformMap[$platform])) {
                $orderedPlatforms[] = $platform;
                unset($platformMap[$platform]);
            }
        }
        $orderedPlatforms = array_merge($orderedPlatforms, array_keys($platformMap));

        $configuredOrder = array_keys(config('lang.languages', []));

        $result = [];
        foreach ($orderedPlatforms as $platform) {
            $languages = [];
            foreach ($localizedFields as $fieldValues) {
                foreach (($fieldValues[$platform] ?? []) as $lang => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $languages[$lang] = true;
                }
            }

            $orderedLangs = [];
            foreach ($configuredOrder as $lang) {
                if (isset($languages[$lang])) {
                    $orderedLangs[] = $lang;
                    unset($languages[$lang]);
                }
            }
            $orderedLangs = array_merge($orderedLangs, array_keys($languages));

            foreach ($orderedLangs as $lang) {
                $linkValueRaw = $localizedFields['link'][$platform][$lang] ?? '';
                $linkValue    = is_scalar($linkValueRaw) ? (string) $linkValueRaw : '';
                $linkType     = $this->detectLinkType($linkValue);
                $result[] = [
                    'platform'          => $platform,
                    'lang'              => $lang,
                    'img_url'           => $localizedFields['img_url'][$platform][$lang] ?? '',
                    'link'              => $linkValue,
                    'link_type'         => $linkType,
                    'target_url'        => $localizedFields['target_url'][$platform][$lang] ?? $linkValue,
                    'target_type'       => $localizedFields['target_type'][$platform][$lang] ?? $linkType,
                    'target_type_name'  => $targetTypeNames[$platform][$lang] ?? '',
                    'target_param'      => $localizedFields['target_param'][$platform][$lang] ?? '',
                ];
            }
        }

        return $result;
    }

    private function detectLinkType(string $value): ?int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (ctype_digit($trimmed)) {
            return 1;
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return 2;
        }

        return null;
    }

    private function buildLocalizedTargetTypeNames(array $targetTypes, array $links): array
    {
        $names = [];
        $platforms = array_unique(array_merge(array_keys($targetTypes), array_keys($links)));

        foreach ($platforms as $platform) {
            $typeLocales = is_array($targetTypes[$platform] ?? null) ? $targetTypes[$platform] : [];
            $linkLocales = is_array($links[$platform] ?? null) ? $links[$platform] : [];
            $langs = array_unique(array_merge(array_keys($typeLocales), array_keys($linkLocales)));
            foreach ($langs as $lang) {
                $type = $typeLocales[$lang] ?? $this->detectLinkType((string) ($linkLocales[$lang] ?? ''));
                if ($type === null) {
                    $names[$platform][$lang] = '';
                    continue;
                }
                $names[$platform][$lang] = trans('ad.target_types.' . $type, [], $lang);
            }
        }

        return $names;
    }

    /**
     * 判断是否过期
     *
     * @param string|null $endTime 结束时间
     *
     * @return bool
     */
    private function isExpired(?string $endTime): bool
    {
        if (empty($endTime)) {
            return false;
        }

        try {
            $end = Carbon::parse($endTime);
        } catch (\Exception $e) {
            return false;
        }

        return $end->isPast();
    }

    /**
     * 获取操作人
     *
     * @param array $recordIds 记录ID
     *
     * @return array
     */
    private function resolveOperatorNames(array $recordIds): array
    {
        $recordIds = array_values(array_unique(array_filter($recordIds, fn ($id) => ! empty($id))));
        if (empty($recordIds)) {
            return [];
        }

        $operators = $this->userOperationLogRepo->getBatchOperateInfo(UserOperationLog::TARGET_TYPE_AD_RECORD, $recordIds, [UserOperationLog::ACTION_TYPE_ADD, UserOperationLog::ACTION_TYPE_UPDATE]);
        $adminUserIds = array_column($operators, 'operator_id', 'target_id');
        $userNames    = $this->adminUserRepo->getColumnByIds(array_values($adminUserIds), 'login_name');

        $result = [];
        foreach ($adminUserIds as $targetId => $adminId) {
            $result[$targetId] = $userNames[$adminId] ?? '';
        }

        return $result;
    }

    /**
     * 记录操作日志
     *
     * @param int    $recordId 记录ID
     * @param string $action   操作
     *
     * @return void
     */
    private function logRecordOperation(int $recordId, string $action, int $status = UserOperationLog::ACTION_TYPE_ADD): void
    {
        $action = sprintf('position_record_%s', $action);
        $this->userOperationLogRepo->addRecord($recordId, UserOperationLog::TARGET_TYPE_AD_RECORD, json_encode(['action' => $action], JSON_UNESCAPED_UNICODE), $status);
    }
}
