<?php

namespace App\Repositories\Api;

use App\Models\Api\Coupon;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Scaffold\Framework\Repositories\Repository;
use Scaffold\Framework\Enums\TypeCoupon;

class CouponRepository extends Repository
{
    public function __construct(Coupon $model)
    {
        parent::__construct($model);
    }

    public function getList(array $filters, int $limit, int $offset): array
    {
        $query = $this->getQuery();

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $this->appendKeywordFilter($query, $this->like($keyword));
        }

        if (($filters['coupon_type'] ?? null) !== null && $filters['coupon_type'] !== '') {
            Coupon::applyCouponTypeFilter($query, 'coupon_type', (int) $filters['coupon_type']);
        }

        if (! empty($filters['coupon_ids'] ?? [])) {
            $query->whereIn('id', $filters['coupon_ids']);
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        if (($filters['expired_status'] ?? null) !== null && $filters['expired_status'] !== '') {
            $this->appendExpiredStatusFilter($query, (int) $filters['expired_status']);
        }

        $count = $query->count();
        $data = $query->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->all();

        return [$data, $count];
    }

    public function findById(int $id): ?Coupon
    {
        return $this->getQuery()->find($id);
    }

    public function createCoupon(array $data): Coupon
    {
        /** @var Coupon $coupon */
        $coupon = $this->getQuery(true)->create($data);

        return $coupon;
    }

    public function updateById(int $id, array $data, ?Coupon $coupon = null): ?Coupon
    {
        $coupon = $coupon ?: $this->findById($id);
        if (! $coupon) {
            return null;
        }

        $coupon->fill($data);
        $coupon->save();

        return $coupon;
    }

    public function existsByInternalNameAndType(string $nameInternal, int $couponType, ?int $excludeId = null): bool
    {
        $query = $this->getQuery()
            ->where('name_internal', $nameInternal)
            ->where('coupon_type', $couponType);

        if ($excludeId !== null) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    public function getGrantTargetCoupons(): array
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');

        return $this->getQuery()
            ->where('status', Coupon::STATUS_ENABLED)
            ->whereNotNull('start_at')
            ->where('start_at', '<=', $now)
            ->orderBy('start_at')
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function appendKeywordFilter(Builder $query, string $like): void
    {
        $query->where(function (Builder $builder) use ($like): void {
            $builder->where('name_internal', 'like', $like);

            foreach ($this->searchLanguages() as $lang) {
                $builder->orWhere("name_external->$lang", 'like', $like);
                $builder->orWhere("description->$lang", 'like', $like);
            }
        });
    }

    private function appendExpiredStatusFilter(Builder $query, int $expiredStatus): void
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        if ($expiredStatus === Coupon::EXPIRED_STATUS_EXPIRED) {
            $query->whereNotNull('end_at')->where('end_at', '<', $now);
            return;
        }

        $query->where(function (Builder $builder) use ($now): void {
            $builder->whereNull('end_at')->orWhere('end_at', '>=', $now);
        });
    }

    private function like(string $value): string
    {
        return sprintf('%%%s%%', addcslashes($value, '%_'));
    }

    private function searchLanguages(): array
    {
        $languages = array_keys(config('lang.languages', []));
        if (! empty($languages)) {
            return $languages;
        }

        return [config('app.locale', 'en_US')];
    }
}
