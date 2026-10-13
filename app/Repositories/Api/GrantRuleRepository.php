<?php

namespace App\Repositories\Api;

use App\Models\Api\GrantRule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Enums\TypeGrantEvent;
use Scaffold\Framework\Repositories\Repository;

class GrantRuleRepository extends Repository
{
    public function __construct(GrantRule $model)
    {
        parent::__construct($model);
    }

    public function getEnabledUnfinishedNamesByCouponId(int $couponId): array
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');

        return $this->getQuery()
            ->where('target_type', GrantRule::TARGET_COUPON)
            ->where('target', $couponId)
            ->where('status', GrantRule::STATUS_ENABLED)
            ->where(function ($query) use ($now): void {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>=', $now);
            })
            ->orderBy('id')
            ->pluck('name')
            ->filter(static fn (mixed $name): bool => is_string($name) && trim($name) !== '')
            ->map(static fn (string $name): string => trim($name))
            ->values()
            ->all();
    }

    public function findById(int $id): ?GrantRule
    {
        return $this->getQuery()->find($id);
    }

    public function createGrantRule(array $data): GrantRule
    {
        /** @var GrantRule $grantRule */
        $grantRule = $this->getQuery(true)->create($data);

        return $grantRule;
    }

    public function updateById(int $id, array $data, ?GrantRule $grantRule = null): ?GrantRule
    {
        $grantRule = $grantRule ?: $this->findById($id);
        if (! $grantRule) {
            return null;
        }

        $grantRule->fill($data);
        $grantRule->save();

        return $grantRule;
    }

    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        $query = $this->getQuery()->where('name', $name);

        if ($excludeId !== null) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    public function getList(array $filters, int $limit, int $offset): array
    {
        $postCountQuery = DB::connection(DB_API)
            ->table('user_coupon')
            ->selectRaw('rule_id, COUNT(*) as post_count')
            ->groupBy('rule_id');

        $query = $this->getQuery()
            ->from('grant_rule as gr')
            ->leftJoin('coupon as c', function ($join): void {
                $join->on('c.id', '=', 'gr.target')
                    ->where('gr.target_type', '=', GrantRule::TARGET_COUPON);
            })
            ->leftJoinSub($postCountQuery, 'uc', function ($join): void {
                $join->on('uc.rule_id', '=', 'gr.id');
            });

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where('gr.name', 'like', $this->like($keyword));
        }

        if (($filters['target_type'] ?? null) !== null && $filters['target_type'] !== '') {
            $query->where('gr.target_type', (int) $filters['target_type']);
        }

        if (($filters['coupon_type'] ?? null) !== null && $filters['coupon_type'] !== '') {
            $query->where('c.coupon_type', (int) $filters['coupon_type']);
        }

        if (($filters['target'] ?? null) !== null && $filters['target'] !== '') {
            $query->where('gr.target', (int) $filters['target']);
        }

        if (($filters['grant_method'] ?? null) !== null && $filters['grant_method'] !== '') {
            $grantMethod = (int) $filters['grant_method'];
            if ($grantMethod === GrantRule::GRANT_METHOD_MANUAL) {
                $query->where('gr.event_key', TypeGrantEvent::Manual->value);
            } elseif ($grantMethod === GrantRule::GRANT_METHOD_AUTO) {
                $query->where(function ($builder): void {
                    $builder->whereNull('gr.event_key')
                        ->orWhere('gr.event_key', '<>', TypeGrantEvent::Manual->value);
                });
            }
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('gr.status', (int) $filters['status']);
        }

        if (! empty($filters['rule_ids'] ?? [])) {
            $query->whereIn('gr.id', $filters['rule_ids']);
        }

        $count = (clone $query)->count('gr.id');
        $data = $query
            ->select([
                'gr.id',
                'gr.name',
                'gr.target_type',
                'gr.target',
                'gr.event_key',
                'gr.event_params',
                'gr.status',
                'gr.total_count',
                'gr.granted_count',
                'gr.start_at',
                'gr.end_at',
                'c.name_internal as coupon_name',
                'c.coupon_type',
                DB::raw('COALESCE(uc.post_count, 0) as post_count'),
            ])
            ->orderByDesc('gr.id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->all();

        return [$data, $count];
    }

    private function like(string $value): string
    {
        return sprintf('%%%s%%', addcslashes($value, '%_'));
    }
}
