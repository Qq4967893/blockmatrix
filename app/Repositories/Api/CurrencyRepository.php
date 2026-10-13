<?php

namespace App\Repositories\Api;

use App\Models\Api\Currency;
use Scaffold\Framework\Repositories\Repository;

class CurrencyRepository extends Repository
{
    public function __construct(Currency $model)
    {
        parent::__construct($model);
    }

    /**
     * @return array{data: array<int, array>, total_count: int}
     */
    public function paginate(array $filters, int $limit, int $offset): array
    {
        $query = $this->getQuery()->from('currency');

        if (! empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        $count = (clone $query)->count('id');

        $data = $query->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(static fn($item) => is_array($item) ? $item : $item->getAttributes())
            ->all();

        return [
            'data'        => $data,
            'total_count' => $count,
        ];
    }

    public function findByCurrency(string $currency): ?array
    {
        $row = $this->getQuery()
            ->where('currency', $currency)
            ->first();

        return $row ? (is_array($row) ? $row : $row->getAttributes()) : null;
    }

    public function find(int $id): ?array
    {
        $row = $this->getQuery()->find($id);

        return $row ? (is_array($row) ? $row : $row->getAttributes()) : null;
    }

    public function create(array $payload): int
    {
        $model = $this->getQuery()->create($payload);

        return (int) ($model->id ?? 0);
    }
}
