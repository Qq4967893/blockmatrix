<?php

namespace App\Repositories\Api;

use Scaffold\Framework\Models\Api\LoginLog;
use Scaffold\Framework\Repositories\Repository;

class LoginLogRepository extends Repository
{
    public function __construct(LoginLog $model)
    {
        parent::__construct($model);
    }

    /**
     * 用户登录日志列表
     *
     * @param array{
     *   keywords?: string|null,
     *   keyword_type?: string|null,
     *   is_latest?: int|string|null,
     *   country?: string|null,
     *   login_type?: string|null,
     *   login_time_start?: string|null,
     *   login_time_end?: string|null
     * } $filters 筛选条件
     * @param int $limit  每页条数
     * @param int $offset 分页偏移量
     *
     * @return array{0: array<int, object>, 1: int}
     */
    public function getList(array $filters, int $limit, int $offset): array
    {
        $query = $this->getQuery()
            ->from('login_log as ll')
            ->leftJoin('user as u', 'u.user_code', '=', 'll.user_code')
            ->select('ll.*', 'u.email');

        $keywords = trim((string) ($filters['keywords'] ?? ''));
        if ($keywords !== '') {
            $this->appendKeywordFilter(
                $query,
                $keywords,
                (string) ($filters['keyword_type'] ?? 'all')
            );
        }

        if (($filters['is_latest'] ?? null) !== null && $filters['is_latest'] !== '') {
            $query->where('ll.is_latest', (int) $filters['is_latest']);
        }

        $country = strtoupper(trim((string) ($filters['country'] ?? '')));
        if ($country !== '') {
            $query->where('ll.country', $country);
        }

        $loginType = trim((string) ($filters['login_type'] ?? ''));
        if ($loginType !== '') {
            $query->where('ll.login_type', $loginType);
        }

        if (! empty($filters['login_time_start'])) {
            $query->where('ll.created_at', '>=', (string) $filters['login_time_start']);
        }

        if (! empty($filters['login_time_end'])) {
            $query->where('ll.created_at', '<=', (string) $filters['login_time_end']);
        }

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('ll.created_at')
            ->orderByDesc('ll.id')
            ->offset(max(0, $offset))
            ->limit(max(1, $limit))
            ->get()
            ->all();

        return [$rows, $total];
    }

    private function appendKeywordFilter(mixed $query, string $keywords, string $keywordType): void
    {
        $keywordColumns = [
            'request_id' => 'll.request_id',
            'user_code'  => 'll.user_code',
            'ip'         => 'll.ip',
            'user_agent' => 'll.user_agent',
        ];

        if ($keywordType !== 'all' && ! array_key_exists($keywordType, $keywordColumns)) {
            $keywordType = 'all';
        }

        if ($keywordType === 'user_code') {
            $this->appendExactNumberFilter($query, $keywordColumns[$keywordType], $keywords);
            return;
        }

        if ($keywordType !== 'all') {
            $query->where($keywordColumns[$keywordType], 'like', $this->like($keywords));
            return;
        }

        $query->where(function ($subQuery) use ($keywords, $keywordColumns) {
            if (is_numeric($keywords)) {
                $subQuery->where($keywordColumns['user_code'], (int) $keywords);
            }

            $subQuery->orWhere($keywordColumns['request_id'], 'like', $this->like($keywords))
                ->orWhere($keywordColumns['ip'], 'like', $this->like($keywords))
                ->orWhere($keywordColumns['user_agent'], 'like', $this->like($keywords));
        });
    }

    private function appendExactNumberFilter(mixed $query, string $column, string $keywords): void
    {
        if (! is_numeric($keywords)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where($column, (int) $keywords);
    }

    private function like(string $value): string
    {
        return sprintf('%%%s%%', addcslashes($value, '%_'));
    }
}
