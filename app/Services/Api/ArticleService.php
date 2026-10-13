<?php

namespace App\Services\Api;

use App\Exceptions\BusinessException;
use App\Models\Api\Article;
use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\ArticleRepository;
use App\Repositories\Blockmatrix\AdminUserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Enums\TypeArticleCategory;
use Scaffold\Framework\Utils\S3;

class ArticleService
{
    public function __construct(
        private ArticleRepository $articleRepo,
        private AdminUserRepository $adminUserRepo,
        private UserOperationLogRepository $operationLogRepo,
        private S3 $s3,
    ) {}

    public function options(): array
    {
        return [
            'category_options' => TypeArticleCategory::details(),
            'status_options' => Article::statusOptions(),
        ];
    }

    public function getList(array $filters, int $limit, int $offset): array
    {
        [$records, $count] = $this->articleRepo->getList($filters, $limit, $offset);
        $publisherNames = $this->resolvePublisherNames($records);

        return [
            'data' => array_map(
                fn (Article $article): array => $this->formatArticle($article, $publisherNames),
                $records,
            ),
            'total_count' => $count,
        ];
    }

    public function create(array $payload): bool
    {
        $article = $this->articleRepo->createArticle(
            $this->normalizePayload($payload) + ['status' => Article::STATUS_DRAFT],
        );
        $this->logOperation((int) $article->id, 'create', UserOperationLog::ACTION_TYPE_ADD);

        return true;
    }

    public function update(int $id, array $payload): ?bool
    {
        $updated = DB::connection(DB_API)->transaction(function () use ($id, $payload): ?Article {
            $article = $this->articleRepo->findByIdForUpdate($id);
            if (! $article) {
                return null;
            }

            $this->ensureNotDeleted($article);

            return $this->articleRepo->updateById($id, $this->normalizePayload($payload), $article);
        });

        if ($updated) {
            $this->logOperation($id, 'update', UserOperationLog::ACTION_TYPE_UPDATE);
        }

        return $updated !== null;
    }

    public function updateStatus(int $id, int $status): ?array
    {
        if (! in_array($status, Article::STATUS_UPDATE_OPTIONS, true)) {
            throw new BusinessException(trans('article.validation.status_in'));
        }

        $action = match ($status) {
            Article::STATUS_PUBLISHED => 'publish',
            Article::STATUS_OFFLINE => 'offline',
            Article::STATUS_DELETED => 'delete',
        };

        $updated = DB::connection(DB_API)->transaction(function () use ($id, $status): ?Article {
            $article = $this->articleRepo->findByIdForUpdate($id);
            if (! $article) {
                return null;
            }

            $this->ensureNotDeleted($article);

            $update = match ($status) {
                Article::STATUS_PUBLISHED => $this->publishUpdate($article),
                Article::STATUS_OFFLINE => $this->offlineUpdate($article),
                Article::STATUS_DELETED => ['status' => Article::STATUS_DELETED],
                default => [],
            };

            return $this->articleRepo->updateById($id, $update, $article);
        });

        if (! $updated) {
            return null;
        }

        $this->logOperation(
            $id,
            $action,
            $status === Article::STATUS_DELETED
                ? UserOperationLog::ACTION_TYPE_DELETE
                : UserOperationLog::ACTION_TYPE_UPDATE,
        );

        return [
            'id' => (int) $updated->id,
            'status' => (int) $updated->status,
            'status_name' => trans('article.status.'.(int) $updated->status),
            'published_at' => $this->formatDate($updated->published_at),
            'published_by' => (int) $updated->published_by,
            'published_by_name' => $this->adminName((int) $updated->published_by),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publishUpdate(Article $article): array
    {
        if (! in_array((int) $article->status, Article::PUBLISHABLE_STATUSES, true)) {
            throw new BusinessException(trans('article.message.only_draft_or_offline_can_publish'));
        }

        return [
            'status' => Article::STATUS_PUBLISHED,
            'published_at' => Carbon::now(),
            'published_by' => $this->currentAdminUserId(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function offlineUpdate(Article $article): array
    {
        if ((int) $article->status !== Article::STATUS_PUBLISHED) {
            throw new BusinessException(trans('article.message.only_published_can_offline'));
        }

        return ['status' => Article::STATUS_OFFLINE];
    }

    private function normalizePayload(array $payload): array
    {
        $locales = $this->normalizeLocalePayload($payload);

        // 缩略图未传或为空时不写字段：修改保留原值，新增走数据库默认值。
        $data = [
            'category' => $this->normalizeCategories($payload['category'] ?? []),
            'is_top' => (int) ($payload['is_top'] ?? 0),
            'title' => $locales['title'],
            'content' => $locales['content'],
            'published_by' => $this->currentAdminUserId(),
        ];

        $thumbnail = is_scalar($payload['thumbnail'] ?? null) ? trim((string) $payload['thumbnail']) : '';
        if ($thumbnail !== '') {
            $data['thumbnail'] = $thumbnail;
        }

        return $data;
    }

    private function normalizeLocalePayload(array $payload): array
    {
        if (! empty($payload['locale_configs']) && is_array($payload['locale_configs'])) {
            $titles = [];
            $contents = [];
            foreach ($payload['locale_configs'] as $config) {
                if (! is_array($config)) {
                    continue;
                }

                $lang = trim((string) ($config['lang'] ?? ''));
                if ($lang === '') {
                    continue;
                }

                $titles[$lang] = (string) ($config['title'] ?? '');
                $contents[$lang] = (string) ($config['content'] ?? '');
            }

            return [
                'title' => $this->filterLocaleValues($titles),
                'content' => $this->filterLocaleValues($contents),
            ];
        }

        return [
            'title' => $this->filterLocaleValues(is_array($payload['title'] ?? null) ? $payload['title'] : []),
            'content' => $this->filterLocaleValues(
                is_array($payload['content'] ?? null) ? $payload['content'] : [],
            ),
        ];
    }

    private function normalizeCategories(mixed $categories): array
    {
        $validCategories = array_map(
            static fn (TypeArticleCategory $category): int => $category->value,
            TypeArticleCategory::cases(),
        );

        $values = is_array($categories) ? $categories : [];

        return array_values(array_unique(array_filter(
            array_map('intval', $values),
            static fn (int $category): bool => in_array($category, $validCategories, true),
        )));
    }

    private function formatArticle(Article $article, array $publisherNames): array
    {
        $categories = array_map('intval', is_array($article->category) ? $article->category : []);
        $titles = $this->filterLocaleValues(is_array($article->title) ? $article->title : []);
        $contents = $this->filterLocaleValues(is_array($article->content) ? $article->content : []);

        return [
            'id' => (int) $article->id,
            'category' => $categories,
            'category_text' => $this->categoryText($categories),
            'title' => $this->displayValue($titles),
            'is_top' => (int) $article->is_top,
            'thumbnail' => $this->fileUrl($article->thumbnail),
            'locale_configs' => $this->buildLocaleConfigs(
                $titles,
                $contents,
                $this->fileUrl($article->thumbnail),
            ),
            'status' => (int) $article->status,
            'status_name' => trans('article.status.'.(int) $article->status),
            'published_at' => $this->formatDate($article->published_at),
            'published_by' => (int) $article->published_by,
            'published_by_name' => $publisherNames[(int) $article->published_by] ?? '',
            'created_at' => $this->formatDate($article->created_at),
            'updated_at' => $this->formatDate($article->updated_at),
        ];
    }

    private function buildLocaleConfigs(array $titles, array $contents, string $thumbnail): array
    {
        $result = [];
        foreach ($this->orderedLanguages(array_merge(array_keys($titles), array_keys($contents))) as $lang) {
            $result[] = [
                'lang' => $lang,
                'title' => (string) ($titles[$lang] ?? ''),
                'content' => (string) ($contents[$lang] ?? ''),
                'thumbnail' => $thumbnail,
            ];
        }

        return $result;
    }

    private function categoryText(array $categories): string
    {
        $labels = [];
        foreach ($categories as $category) {
            $enum = TypeArticleCategory::tryFrom($category);
            if ($enum) {
                $labels[] = $enum->label();
            }
        }

        return implode(', ', $labels);
    }

    private function displayValue(array $values): string
    {
        $currentLocale = app()->getLocale() ?: config('app.locale', 'en_US');
        $fallback = config('app.fallback_locale', 'en_US');

        foreach ([$currentLocale, $fallback] as $locale) {
            if (isset($values[$locale]) && $values[$locale] !== '') {
                return (string) $values[$locale];
            }
        }

        $first = reset($values);

        return is_scalar($first) ? (string) $first : '';
    }

    private function fileUrl(mixed $value): string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if ($value === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }

        return (string) ($this->s3->fileUrl($value) ?? '');
    }

    private function filterLocaleValues(array $values): array
    {
        $result = [];
        foreach ($values as $lang => $value) {
            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            $result[(string) $lang] = (string) $value;
        }

        return $result;
    }

    private function orderedLanguages(array $languages): array
    {
        $languages = array_unique(array_map('strval', $languages));
        $configured = array_keys(config('lang.languages', []));
        $ordered = array_values(array_intersect($configured, $languages));

        return array_merge($ordered, array_values(array_diff($languages, $configured)));
    }

    private function resolvePublisherNames(array $records): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (Article $article): int => (int) $article->published_by,
            $records,
        ))));
        if ($ids === []) {
            return [];
        }

        return $this->adminUserRepo->getColumnByIds($ids, 'login_name');
    }

    private function adminName(int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        return (string) ($this->adminUserRepo->getColumnByIds([$id], 'login_name')[$id] ?? '');
    }

    private function currentAdminUserId(): int
    {
        $user = Auth::user();

        return (int) (is_array($user) ? ($user['id'] ?? 0) : ($user->id ?? 0));
    }

    private function ensureNotDeleted(Article $article): void
    {
        if ((int) $article->status === Article::STATUS_DELETED) {
            throw new BusinessException(trans('article.message.deleted_not_operable'));
        }
    }

    private function logOperation(int $articleId, string $action, int $actionType): void
    {
        $content = json_encode(
            ['action' => sprintf('article_%s', $action)],
            JSON_UNESCAPED_UNICODE,
        );

        $this->operationLogRepo->addRecord(
            $articleId,
            UserOperationLog::TARGET_TYPE_ARTICLE,
            $content,
            $actionType,
        );
    }

    private function formatDate(mixed $date): string
    {
        if (! $date instanceof Carbon) {
            return (string) $date;
        }

        return $date->format('Y-m-d H:i:s');
    }
}
