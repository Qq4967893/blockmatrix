<?php

namespace App\Repositories\Api;

use App\Models\Api\Source;
use Illuminate\Database\Eloquent\Builder;
use Scaffold\Framework\Repositories\Repository;

class SourceRepository extends Repository
{
    public function __construct(Source $model)
    {
        parent::__construct($model);
    }

    /**
     * 获取品种列表
     */
    public function getList(
        string $sourceCode,
        string $nameKeyword,
        string $sourceType,
        ?int $status,
        int $limit,
        int $offset
    ): array {
        $query = $this->getQuery()
            ->select('*', 'category as source_type');

        if ($sourceCode !== '') {
            $query->where('source', 'like', $this->like($sourceCode));
        }

        if ($nameKeyword !== '') {
            $this->appendNameKeywordFilter($query, $nameKeyword);
        }

        if ($sourceType !== '') {
            $query->where('category', $sourceType);
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $data  = $query->orderBy('source')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();

        return [$data, $total];
    }

    public function getAll(){
        return $this->getQuery()
            ->select('source', 'category as source_type', 'config')
            ->get()
            ->toArray();
    }

    /**
     * 获取全部品种代码。
     *
     * @return array<int, string>
     */
    public function getAllSources(): array
    {
        return $this->getQuery()
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->orderBy('source')
            ->pluck('source')
            ->map(static fn ($source): string => (string) $source)
            ->all();
    }

    public function findById(int $id): ?Source
    {
        return $this->getQuery()->find($id);
    }

    public function updateById(int $id, array $payload): bool
    {
        return (bool) $this->getQuery()->where('id', $id)->update($payload);
    }

    /**
     * 获取已有的分类
     */
    public function getDistinctTypes(): array
    {
        return $this->getQuery()
            ->select('category as source_type')
            ->distinct()
            ->orderBy('category')
            ->pluck('source_type')
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->values()
            ->toArray();
    }

    private function appendNameKeywordFilter(Builder $query, string $keyword): void
    {
        $keyword = $this->like($keyword);
        $langs   = array_keys(config('lang.languages', []));
        $query->where(function (Builder $builder) use ($keyword, $langs) {
            if (empty($langs)) {
                $defaultLang = config('app.locale', 'en_US');
                $builder->where("name->$defaultLang", 'like', $keyword);
                return;
            }

            foreach ($langs as $lang) {
                $builder->orWhere("name->$lang", 'like', $keyword);
            }
        });
    }

    private function like(string $value): string
    {
        $escaped = addcslashes($value, '%_');

        return sprintf('%%%s%%', $escaped);
    }
}
