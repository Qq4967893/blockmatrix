<?php

namespace App\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Api\CommunityActivity;
use App\Models\Api\CommunityActivityApplication;
use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\CommunityActivityApplicationRepository;
use App\Repositories\Api\CommunityActivityRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Utils\S3FileKey;
use BackedEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Enums\TypeCommunityActivity;
use Scaffold\Framework\Enums\TypeCommunityActivityAudience;
use Scaffold\Framework\Enums\TypeCommunityActivityCity;
use Scaffold\Framework\Utils\S3;

class CommunityActivityService
{
    public function __construct(
        private CommunityActivityRepository $activityRepo,
        private CommunityActivityApplicationRepository $applicationRepo,
        private UserOperationLogRepository $operationLogRepo,
    ) {}

    /**
     * 活动筛选选项
     */
    public function options(): array
    {
        return [
            'status_options' => [
                ['code' => CommunityActivity::STATUS_PENDING, 'label' => $this->statusLabel(CommunityActivity::STATUS_PENDING)],
                ['code' => CommunityActivity::STATUS_ONLINE, 'label' => $this->statusLabel(CommunityActivity::STATUS_ONLINE)],
                ['code' => CommunityActivity::STATUS_VOIDED, 'label' => $this->statusLabel(CommunityActivity::STATUS_VOIDED)],
            ],
            'type_options' => array_map(
                static fn (TypeCommunityActivity $type): array => ['code' => $type->value, 'label' => $type->label()],
                TypeCommunityActivity::cases()
            ),
            'city_options' => array_map(
                static fn (TypeCommunityActivityCity $city): array => ['code' => $city->value, 'label' => $city->label()],
                TypeCommunityActivityCity::cases()
            ),
            'audience_options' => array_map(
                static fn (TypeCommunityActivityAudience $audience): array => ['key' => $audience->value, 'value' => $audience->label()],
                TypeCommunityActivityAudience::cases()
            ),
        ];
    }

    /**
     * 活动列表
     *
     * @param  array{keyword?: string, type?: int|null, status?: int|null, city?: string|null}  $filters
     * @return array{data: array<int, array<string, mixed>>, total_count: int}
     */
    public function index(array $filters, int $limit, int $offset): array
    {
        $now = Carbon::now();
        [$rows, $count] = $this->activityRepo->getList(
            (string) ($filters['keyword'] ?? ''),
            $this->nullableInt($filters['type'] ?? null),
            $this->nullableInt($filters['status'] ?? null),
            isset($filters['city']) && $filters['city'] !== '' ? (string) $filters['city'] : null,
            $limit,
            $offset,
            $now
        );

        $operators = $this->latestOperators($rows->pluck('id')->all());

        return [
            'data' => array_map(
                fn (CommunityActivity $activity): array => $this->formatActivity($activity, $now, $operators),
                $rows->all()
            ),
            'total_count' => $count,
        ];
    }

    /**
     * 活动报名列表
     *
     * @param  array{community_activity_id?: int|null, keyword?: string}  $filters
     * @return array{data: array<int, array<string, mixed>>, total_count: int}
     */
    public function applications(array $filters, int $limit, int $offset): array
    {
        [$rows, $count] = $this->applicationRepo->getList(
            $this->nullableInt($filters['community_activity_id'] ?? null),
            (string) ($filters['keyword'] ?? ''),
            $limit,
            $offset
        );

        return [
            'data' => array_map(
                fn (CommunityActivityApplication $application): array => $this->formatApplication($application),
                $rows->all()
            ),
            'total_count' => $count,
        ];
    }

    /**
     * 新增活动
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $data = $this->normalizePayload($payload);
        $data['status'] = $this->statusForActivityTime($data['start_at'], $data['end_at']);
        $activity = $this->activityRepo->create($data);
        $this->logOperation((int) $activity->id, 'create', UserOperationLog::ACTION_TYPE_ADD);

        return $this->formatActivity($activity);
    }

    /**
     * 修改活动
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $payload): ?array
    {
        $activity = $this->activityRepo->findById($id);
        if (! $activity) {
            return null;
        }

        $data = $this->normalizePayload($payload);
        if ((int) $activity->status === CommunityActivity::STATUS_VOIDED) {
            $data['status'] = CommunityActivity::STATUS_VOIDED;
        } elseif ($this->isManuallyOnline($activity)) {
            // 人工提前发布的活动保持上线，不随编辑时间回退为待上线
            $data['status'] = CommunityActivity::STATUS_ONLINE;
        } else {
            $data['status'] = $this->statusForActivityTime($data['start_at'], $data['end_at']);
        }

        $updated = $this->activityRepo->updateById($id, $data, $activity);
        if ($updated === null) {
            return null;
        }

        $this->logOperation($id, 'update', UserOperationLog::ACTION_TYPE_UPDATE);

        return $this->formatActivity($updated);
    }

    /**
     * 发布活动（人工提前上线）
     *
     * @return array<string, mixed>|null
     */
    public function publish(int $id): ?array
    {
        $published = DB::connection(DB_API)->transaction(function () use ($id): ?CommunityActivity {
            $activity = $this->activityRepo->findByIdForUpdate($id);
            if (! $activity) {
                return null;
            }

            $this->ensurePublishable($activity);

            return $this->activityRepo->updateById($id, [
                'status' => CommunityActivity::STATUS_ONLINE,
            ], $activity);
        });

        if ($published === null) {
            return null;
        }

        $this->logOperation($id, 'publish', UserOperationLog::ACTION_TYPE_UPDATE);

        return $this->formatActivity($published);
    }

    /**
     * 作废活动
     */
    public function voidActivity(int $id): ?bool
    {
        $activity = $this->activityRepo->findById($id);
        if (! $activity) {
            return null;
        }

        $this->ensureVoidable($activity);

        $updated = $this->activityRepo->updateById($id, [
            'status' => CommunityActivity::STATUS_VOIDED,
        ], $activity);
        if ($updated === null) {
            return null;
        }

        $this->logOperation($id, 'void', UserOperationLog::ACTION_TYPE_UPDATE);

        return true;
    }

    /**
     * 定时刷新活动状态。
     *
     * @return array{voided: int, online: int}
     */
    public function updateActivityStatuses(?Carbon $now = null): array
    {
        return $this->activityRepo->updateStatusesByActivityTime($now ?? Carbon::now());
    }

    private function formatActivity(CommunityActivity $activity, ?Carbon $now = null, ?array $operators = null): array
    {
        $name = $this->normalizeLocalized($activity->name);
        $summary = $this->normalizeLocalized($activity->summary);
        $coverImage = $this->formatLocalizedImage($activity->cover_image);
        $bgImage = $this->formatLocalizedImage($activity->bg_image);
        $introduction = $this->normalizeLocalized($activity->introduction);
        $content = $this->normalizeLocalized($activity->content);
        $type = $activity->type;
        $city = $activity->city;
        $status = $this->effectiveActivityStatus($activity, $now);
        $publishType = $this->isManuallyOnline($activity, $now)
            ? CommunityActivity::PUBLISH_TYPE_MANUAL
            : CommunityActivity::PUBLISH_TYPE_SCHEDULED;
        $operators ??= $this->latestOperators([(int) $activity->id]);
        $operator = $operators[(int) $activity->id] ?? ['operator_id' => 0, 'operator_name' => ''];

        return [
            'id' => (int) $activity->id,
            'name_text' => $this->localizedText($name),
            'name' => $name,
            'type' => $this->enumValue($type, 0),
            'type_text' => $type instanceof TypeCommunityActivity ? $type->label() : '',
            'city' => $this->enumValue($city, ''),
            'city_text' => $city instanceof TypeCommunityActivityCity ? $city->label() : '',
            'activity_time' => sprintf(
                '%s - %s',
                $this->formatDateTime($activity->start_at) ?? '',
                $this->formatDateTime($activity->end_at) ?? ''
            ),
            'start_at' => $this->formatDateTime($activity->start_at) ?? '',
            'end_at' => $this->formatDateTime($activity->end_at) ?? '',
            'apply_start_at' => $this->formatDateTime($activity->apply_start_at) ?? '',
            'apply_end_at' => $this->formatDateTime($activity->apply_end_at) ?? '',
            'status' => $status,
            'status_text' => $this->statusLabel($status),
            'status_code' => $status,
            'publish_type' => $publishType,
            'publish_type_text' => $this->publishTypeLabel($publishType),
            'operator_id' => (int) $operator['operator_id'],
            'operator_name' => (string) $operator['operator_name'],
            'audience' => $this->normalizeAudience($activity->audience),
            'audience_text' => $this->audienceText($activity->audience),
            'summary' => $summary,
            'summary_text' => $this->localizedText($summary),
            'cover_image' => $coverImage,
            'bg_image' => $bgImage,
            'introduction' => $introduction,
            'introduction_text' => $this->localizedText($introduction),
            'content' => $content,
            'content_text' => $this->localizedText($content),
            'locale_configs' => $this->buildLocaleConfigs([
                'name' => $name,
                'summary' => $summary,
                'cover_image' => $coverImage,
                'bg_image' => $bgImage,
                'introduction' => $introduction,
                'content' => $content,
            ]),
            'created_at' => $this->formatDateTime($activity->created_at) ?? '',
            'updated_at' => $this->formatDateTime($activity->updated_at) ?? '',
        ];
    }

    private function ensureVoidable(CommunityActivity $activity): void
    {
        if ((int) $activity->status === CommunityActivity::STATUS_VOIDED) {
            throw new BusinessException(trans('community_activity.message.voided_no_action'));
        }
    }

    private function ensurePublishable(CommunityActivity $activity): void
    {
        if ((int) $activity->status === CommunityActivity::STATUS_VOIDED) {
            throw new BusinessException(trans('community_activity.message.voided_no_action'));
        }

        if ((int) $activity->status === CommunityActivity::STATUS_ONLINE) {
            throw new BusinessException(trans('community_activity.message.only_pending_can_publish'));
        }

        if (Carbon::now()->greaterThan(Carbon::parse($activity->end_at))) {
            throw new BusinessException(trans('community_activity.message.activity_ended'));
        }
    }

    private function isManuallyOnline(CommunityActivity $activity, ?Carbon $now = null): bool
    {
        // 纯逻辑判断：上线状态且未到开始时间，只可能是人工提前发布
        $now ??= Carbon::now();

        return (int) $activity->status === CommunityActivity::STATUS_ONLINE
            && $now->lessThan(Carbon::parse($activity->start_at));
    }

    /**
     * 从 user_operation_log 获取活动最后操作人（blockmatrix 库，不落 API 库字段）
     *
     * @param  array<int, int>  $activityIds
     * @return array<int, array{operator_id: int, operator_name: string}>
     */
    private function latestOperators(array $activityIds): array
    {
        $operators = $this->operationLogRepo->getLatestOperateInfo(
            UserOperationLog::TARGET_TYPE_COMMUNITY_ACTIVITY,
            $activityIds,
            [UserOperationLog::ACTION_TYPE_ADD, UserOperationLog::ACTION_TYPE_UPDATE]
        );

        return is_array($operators) ? $operators : [];
    }

    private function logOperation(int $activityId, string $action, int $actionType): void
    {
        $this->operationLogRepo->addRecord(
            $activityId,
            UserOperationLog::TARGET_TYPE_COMMUNITY_ACTIVITY,
            json_encode(['action' => sprintf('community_activity_%s', $action)], JSON_UNESCAPED_UNICODE),
            $actionType,
        );
    }

    private function statusForActivityTime(string $startAt, string $endAt, ?Carbon $now = null): int
    {
        $now ??= Carbon::now();
        $startAt = Carbon::parse($startAt);
        $endAt = Carbon::parse($endAt);

        if ($now->lessThan($startAt)) {
            return CommunityActivity::STATUS_PENDING;
        }

        if ($now->greaterThan($endAt)) {
            return CommunityActivity::STATUS_VOIDED;
        }

        return CommunityActivity::STATUS_ONLINE;
    }

    private function effectiveActivityStatus(CommunityActivity $activity, ?Carbon $now = null): int
    {
        if ((int) $activity->status === CommunityActivity::STATUS_VOIDED) {
            return CommunityActivity::STATUS_VOIDED;
        }

        // 人工提前发布的活动保持上线展示，不受活动开始时间影响
        if ($this->isManuallyOnline($activity, $now)) {
            return CommunityActivity::STATUS_ONLINE;
        }

        return $this->statusForActivityTime(
            $this->formatDateTime($activity->start_at) ?? '',
            $this->formatDateTime($activity->end_at) ?? '',
            $now
        );
    }

    private function formatApplication(CommunityActivityApplication $application): array
    {
        $createdAt = $this->formatDateTime($application->created_at) ?? '';
        $activity = $application->activity;

        return [
            'id' => (int) $application->id,
            'community_activity_id' => (int) $application->community_activity_id,
            'user_code' => (int) $application->user_code,
            'activity_name' => $activity ? $this->localizedText($activity->name) : '',
            'name' => (string) $application->name,
            'email' => (string) $application->email,
            'message' => (string) $application->message,
            'submitted_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $this->formatDateTime($application->updated_at) ?? '',
        ];
    }

    private function normalizePayload(array $payload): array
    {
        $localized = $this->localeConfigsToMaps($payload['locale_configs'] ?? []);

        return [
            'type' => (int) ($payload['type'] ?? 0),
            'city' => (string) ($payload['city'] ?? ''),
            'start_at' => (string) ($payload['start_at'] ?? ''),
            'end_at' => (string) ($payload['end_at'] ?? ''),
            'apply_start_at' => (string) ($payload['apply_start_at'] ?? ''),
            'apply_end_at' => (string) ($payload['apply_end_at'] ?? ''),
            'audience' => array_values(array_unique(array_map(
                'strval',
                $this->normalizeAudience($payload['audience'] ?? [])
            ))),
            'name' => $localized['name'],
            'summary' => $localized['summary'],
            'cover_image' => $localized['cover_image'],
            'bg_image' => $localized['bg_image'],
            'introduction' => $localized['introduction'],
            'content' => $localized['content'],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function localeConfigsToMaps(mixed $localeConfigs): array
    {
        $fields = ['name', 'summary', 'cover_image', 'bg_image', 'introduction', 'content'];
        $localized = array_fill_keys($fields, []);
        if (! is_array($localeConfigs)) {
            return $localized;
        }

        foreach ($localeConfigs as $config) {
            if (! is_array($config)) {
                continue;
            }

            $lang = trim((string) ($config['lang'] ?? ''));
            if ($lang === '') {
                continue;
            }

            foreach ($fields as $field) {
                $value = (string) ($config[$field] ?? '');
                $localized[$field][$lang] = in_array($field, ['cover_image', 'bg_image'], true)
                    ? S3FileKey::normalize($value)
                    : $value;
            }
        }

        return $localized;
    }

    /**
     * @param  array<string, array<string, mixed>>  $localizedFields
     * @return array<int, array<string, string>>
     */
    private function buildLocaleConfigs(array $localizedFields): array
    {
        $languages = [];
        foreach ($localizedFields as $translations) {
            $languages = array_merge($languages, array_keys($translations));
        }
        $languages = array_values(array_unique($languages));

        $configuredLanguages = array_keys(config('lang.languages', []));
        usort($languages, static function (string $left, string $right) use ($configuredLanguages): int {
            $leftIndex = array_search($left, $configuredLanguages, true);
            $rightIndex = array_search($right, $configuredLanguages, true);

            return ($leftIndex === false ? PHP_INT_MAX : $leftIndex)
                <=> ($rightIndex === false ? PHP_INT_MAX : $rightIndex);
        });

        return array_map(static function (string $lang) use ($localizedFields): array {
            $config = ['lang' => $lang];
            foreach ($localizedFields as $field => $translations) {
                $config[$field] = (string) ($translations[$lang] ?? '');
            }

            return $config;
        }, $languages);
    }

    private function statusLabel(int $status): string
    {
        $key = 'community_activity.status.'.$status;
        $label = trans($key);

        return $label === $key ? (string) trans('community_activity.status.unknown') : (string) $label;
    }

    private function publishTypeLabel(int $publishType): string
    {
        $key = 'community_activity.publish_type.'.$publishType;
        $label = trans($key);

        return $label === $key ? (string) trans('community_activity.publish_type.unknown') : (string) $label;
    }

    private function localizedText(mixed $value): string
    {
        $values = $this->normalizeLocalized($value);
        $current = app()->getLocale() ?: config('app.locale');
        $fallback = config('app.fallback_locale', 'en_US');

        foreach (array_unique(array_filter([$current, $fallback, 'en_US', 'zh_CN'])) as $locale) {
            if (isset($values[$locale]) && $values[$locale] !== '' && $values[$locale] !== null) {
                return (string) $values[$locale];
            }
        }

        return '';
    }

    private function normalizeLocalized(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeAudience(mixed $value): array
    {
        $values = $this->normalizeLocalized($value);
        if ($values === []) {
            return [];
        }

        $result = [];
        foreach ($values as $item) {
            if ($item instanceof TypeCommunityActivityAudience) {
                $result[] = $item->value;
            } elseif (is_object($item) && method_exists($item, 'value')) {
                $result[] = (string) $item->value;
            } elseif (is_scalar($item)) {
                $result[] = (string) $item;
            }
        }

        return array_values(array_unique(array_filter($result, static fn (string $item): bool => $item !== '')));
    }

    private function audienceText(mixed $value): string
    {
        $labels = [];
        foreach ($this->normalizeAudience($value) as $item) {
            $labels[] = TypeCommunityActivityAudience::tryFrom($item)?->label() ?? $item;
        }

        return implode(', ', $labels);
    }

    private function decodeLocalizedImage(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function formatLocalizedImage(mixed $value): array
    {
        return $this->mapImageUrls($this->decodeLocalizedImage($value));
    }

    private function mapImageUrls(array $images): array
    {
        foreach ($images as $lang => $path) {
            if (is_array($path)) {
                $images[$lang] = $this->mapImageUrls($path);

                continue;
            }

            $images[$lang] = $this->formatImageUrl(is_scalar($path) ? (string) $path : '');
        }

        return $images;
    }

    private function formatImageUrl(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        return (string) app(S3::class)->fileUrl(ltrim($path, '/'));
    }

    private function enumValue(mixed $value, mixed $default = null): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value ?? $default;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
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
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
