<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Models\Api\IbApplication;
use Scaffold\Framework\Repositories\Repository;

class IbApplicationRepository extends Repository
{
    public function __construct(IbApplication $model)
    {
        parent::__construct($model);
    }

    public function findById(int $id): ?IbApplication
    {
        return $this->getQuery()->find($id);
    }

    public function countByStatus(int $status): int
    {
        return (int) $this->getQuery()
            ->getQuery()
            ->from('ib_application')
            ->where('status', $status)
            ->count('id');
    }

    /**
     * @param array{
     *     user_code?: int|null,
     *     applied_at_start?: string|null,
     *     applied_at_end?: string|null,
     *     status?: int|null,
     *     reviewed_by_ids?: array<int, int>
     * } $filters
     * @return array{0: array<int, array>, 1: int}
     */
    public function getList(array $filters, int $limit, int $offset): array
    {
        $query = $this->getQuery()
            ->getQuery()
            ->from('ib_application');

        if (($filters['user_code'] ?? null) !== null) {
            $query->where('user_code', (int) $filters['user_code']);
        }

        if (($filters['applied_at_start'] ?? '') !== '') {
            $query->where('applied_at', '>=', (string) $filters['applied_at_start']);
        }

        if (($filters['applied_at_end'] ?? '') !== '') {
            $query->where('applied_at', '<=', (string) $filters['applied_at_end']);
        }

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', (int) $filters['status']);
        }

        if (array_key_exists('reviewed_by_ids', $filters) && $filters['reviewed_by_ids'] !== null) {
            $reviewedByIds = $filters['reviewed_by_ids'] ?? [];
            if (empty($reviewedByIds)) {
                return [[], 0];
            }

            $query->whereIn('reviewed_by', $reviewedByIds);
        }

        $total = (clone $query)->count('id');
        $data = $query->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();

        return [$data, $total];
    }

    public function findLatestByUserCode(int $userCode): ?IbApplication
    {
        return $this->getQuery()
            ->where('user_code', $userCode)
            ->orderByDesc('id')
            ->first();
    }

    public function updateApplication(int $id, array $payload, ?IbApplication $application = null): ?IbApplication
    {
        $application ??= $this->findById($id);
        if (! $application) {
            return null;
        }

        $application->fill($payload);
        $application->save();

        return $application->fresh();
    }
}
