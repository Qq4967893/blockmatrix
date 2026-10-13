<?php

namespace App\Repositories\Api;

use App\Models\Api\Article;
use Illuminate\Database\Eloquent\Builder;
use Scaffold\Framework\Repositories\Repository;

class ArticleRepository extends Repository
{
    public function __construct(Article $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{keyword?:string,categories?:array<int,int>,status?:int|null,is_top?:int|null,published_at_from?:string,published_at_to?:string}  $filters
     * @return array{0:array<int,Article>,1:int}
     */
    public function getList(array $filters, int $limit, int $offset): array
    {
        $query = $this->getQuery()
            ->where('status', '<>', Article::STATUS_DELETED);

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $this->appendTitleFilter($query, $this->like($keyword));
        }

        $categories = array_values(array_unique(array_filter(
            array_map('intval', $filters['categories'] ?? []),
            static fn (int $category): bool => $category > 0,
        )));
        if ($categories !== []) {
            $query->where(function (Builder $builder) use ($categories): void {
                foreach ($categories as $category) {
                    $builder->orWhereJsonContains('category', $category);
                }
            });
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (($filters['is_top'] ?? null) !== null && $filters['is_top'] !== '') {
            $query->where('is_top', (int) $filters['is_top']);
        }

        if (! empty($filters['published_at_from'])) {
            $query->where('published_at', '>=', $filters['published_at_from']);
        }

        if (! empty($filters['published_at_to'])) {
            $query->where('published_at', '<=', $filters['published_at_to']);
        }

        $count = (clone $query)->count();
        $records = $query->orderByDesc('is_top')
            ->orderByDesc('id')
            ->offset(max(0, $offset))
            ->limit(max(1, $limit))
            ->get()
            ->all();

        return [$records, $count];
    }

    public function findById(int $id): ?Article
    {
        return $this->getQuery()->find($id);
    }

    public function findByIdForUpdate(int $id): ?Article
    {
        return $this->getQuery(true)
            ->lockForUpdate()
            ->find($id);
    }

    public function createArticle(array $data): Article
    {
        /** @var Article $article */
        $article = $this->getQuery(true)->create($data);

        return $article;
    }

    public function updateById(int $id, array $data, ?Article $article = null): ?Article
    {
        $article = $article ?: $this->findById($id);
        if (! $article) {
            return null;
        }

        $article->fill($data);
        $article->save();

        return $article;
    }

    private function appendTitleFilter(Builder $query, string $like): void
    {
        $query->where(function (Builder $builder) use ($like): void {
            foreach ($this->searchLanguages() as $lang) {
                $builder->orWhere("title->$lang", 'like', $like);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function searchLanguages(): array
    {
        $languages = array_keys(config('lang.languages', []));

        return $languages !== [] ? $languages : [config('app.locale', 'en_US')];
    }

    private function like(string $value): string
    {
        return sprintf('%%%s%%', addcslashes($value, '%_'));
    }
}
