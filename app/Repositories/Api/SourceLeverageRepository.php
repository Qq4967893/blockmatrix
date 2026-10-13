<?php

namespace App\Repositories\Api;

use App\Models\Api\SourceLeverage;
use Scaffold\Framework\Repositories\Repository;

class SourceLeverageRepository extends Repository
{
    public function __construct(SourceLeverage $model)
    {
        parent::__construct($model);
    }

    /**
     * 按品种获取杠杆配置
     *
     * @param array $sources 品种编码列表
     *
     * @return array<string, array<int, array>>
     */
    public function getBySources(array $sources): array
    {
        $sources = array_values(array_unique(array_filter($sources, fn ($item) => $item !== null && $item !== '')));
        if (empty($sources)) {
            return [];
        }

        $records = $this->getQuery()
            ->whereIn('source', $sources)
            ->orderBy('source')
            ->orderBy('group')
            ->orderBy('id')
            ->get()
            ->toArray();

        $grouped = [];
        foreach ($records as $record) {
            $grouped[$record['source']][] = $record;
        }

        return $grouped;
    }
}
