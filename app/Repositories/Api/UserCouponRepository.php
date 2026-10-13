<?php

namespace App\Repositories\Api;

use App\Models\Api\Coupon;
use App\Models\Api\UserCoupon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Scaffold\Framework\Enums\CreditBizType;
use Scaffold\Framework\Enums\TypeCoupon;
use Scaffold\Framework\Repositories\Repository;

class UserCouponRepository extends Repository
{
    public function __construct(UserCoupon $model)
    {
        parent::__construct($model);
    }

    public function getList(array $filters, int $limit, int $offset): array
    {
        $latestUsedRecordQuery = $this->buildLatestUsedRecordQuery($filters);
        $query = $this->getQuery()
            ->from('user_coupon as uc')
            ->leftJoin('coupon as c', 'c.id', '=', 'uc.coupon_id');

        if ($this->hasUsedRecordFilter($filters)) {
            $query->joinSub($latestUsedRecordQuery, 'latest_ucur', function ($join): void {
                $join->on('latest_ucur.user_coupon_id', '=', 'uc.id');
            });
        } else {
            $query->leftJoinSub($latestUsedRecordQuery, 'latest_ucur', function ($join): void {
                $join->on('latest_ucur.user_coupon_id', '=', 'uc.id');
            });
        }

        $query->leftJoin('user_coupon_used_record as ucur', 'ucur.id', '=', 'latest_ucur.id')
            ->leftJoin('trade_account as ta', function ($join): void {
                $join->on('ta.trade_account_id', '=', 'ucur.trade_account_id')
                    ->on('ta.user_code', '=', 'ucur.user_code');
            });

        if (($filters['user_code'] ?? null) !== null && $filters['user_code'] !== '') {
            $query->where('uc.user_code', (int) $filters['user_code']);
        }

        if (! empty($filters['coupon_ids'] ?? [])) {
            $query->whereIn('uc.coupon_id', $filters['coupon_ids']);
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $this->appendCouponKeywordFilter($query, $this->like($keyword));
        }

        $nameInternal = trim((string) ($filters['name_internal'] ?? ''));
        if ($nameInternal !== '') {
            $this->appendNameInternalFilter($query, $this->like($nameInternal));
        }

        $nameExternal = trim((string) ($filters['name_external'] ?? ''));
        if ($nameExternal !== '') {
            $this->appendNameExternalFilter($query, $this->like($nameExternal));
        }

        if (($filters['coupon_type'] ?? null) !== null && $filters['coupon_type'] !== '') {
            Coupon::applyCouponTypeFilter($query, 'uc.coupon_type', (int) $filters['coupon_type']);
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('uc.status', (int) $filters['status']);
        }

        if (($filters['expired_status'] ?? null) !== null && $filters['expired_status'] !== '') {
            $this->appendExpiredStatusFilter($query, (int) $filters['expired_status']);
        }

        if (($filters['redeem_status'] ?? null) !== null
            && $filters['redeem_status'] !== ''
            && (int) $filters['redeem_status'] !== $this->redeemStatusCodeByIndex(0)
        ) {
            $this->appendRedeemStatusFilter($query, (int) $filters['redeem_status']);
        } else {
            $this->appendRedeemedFilter($query);
        }

        $count = (clone $query)->count('uc.id');
        $data = $query
            ->select([
                'uc.id',
                'uc.coupon_id',
                'ucur.biz_id',
                'uc.event_data',
                'ucur.trade_account_id',
                'ta.trade_account_type',
                'uc.user_code',
                DB::raw($this->nameInternalSelectSql() . ' as name_internal'),
                DB::raw($this->nameExternalSelectSql() . ' as name_external'),
                'uc.coupon_type',
                'uc.triggered_at',
                DB::raw('ucur.created_at as used_at'),
                'uc.start_at',
                'uc.end_at',
                'uc.total_amount',
                'uc.used_amount',
                'uc.status',
            ])
            ->orderByDesc('uc.id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->all();

        return [$data, $count];
    }

    public function getGrantRecordList(array $filters, int $limit, int $offset): array
    {
        $query = $this->getQuery()
            ->from('user_coupon as uc')
            ->join('grant_rule as gr', 'gr.id', '=', 'uc.rule_id')
            ->leftJoin('coupon as c', 'c.id', '=', 'uc.coupon_id');

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $like = $this->like($keyword);
                $builder->where('gr.name', 'like', $like)
                    ->orWhere('c.name_internal', 'like', $like);
            });
        }

        $ruleName = trim((string) ($filters['rule_name'] ?? ''));
        if ($ruleName !== '') {
            $query->where('gr.name', 'like', $this->like($ruleName));
        }

        if (($filters['rule_id'] ?? null) !== null && $filters['rule_id'] !== '') {
            $query->where('uc.rule_id', (int) $filters['rule_id']);
        }

        if (($filters['user_code'] ?? null) !== null && $filters['user_code'] !== '') {
            $query->where('uc.user_code', (int) $filters['user_code']);
        }

        if (($filters['target_type'] ?? null) !== null && $filters['target_type'] !== '') {
            $query->where('gr.target_type', (int) $filters['target_type']);
        }

        if (($filters['coupon_type'] ?? null) !== null && $filters['coupon_type'] !== '') {
            Coupon::applyCouponTypeFilter($query, 'c.coupon_type', (int) $filters['coupon_type']);
        }

        if (($filters['target'] ?? null) !== null && $filters['target'] !== '') {
            $query->where('gr.target', (int) $filters['target']);
        }

        if (($filters['status'] ?? null) !== null && $filters['status'] !== '') {
            $query->where('uc.status', (int) $filters['status']);
        }

        $count = (clone $query)->count(DB::raw('distinct uc.id'));
        $data = $query
            ->select([
                'uc.id',
                'uc.rule_id',
                'gr.name as rule_name',
                'gr.target_type',
                'gr.target',
                'c.name_internal as target_name',
                'uc.user_code',
                'uc.triggered_at as received_at',
                'uc.start_at as target_start_at',
                'uc.end_at as target_end_at',
                'uc.status',
            ])
            ->orderByDesc('uc.id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->all();

        return [$data, $count];
    }

    public function getTradeCashbackDetailList(int $userCouponId, array $filters, int $limit, int $offset): array
    {
        $query = $this->getQuery()
            ->from('user_coupon_used_record as ucur')
            ->join('user_coupon as uc', 'uc.id', '=', 'ucur.user_coupon_id')
            ->where('ucur.user_coupon_id', $userCouponId)
            ->whereIn('uc.coupon_type', [
                TypeCoupon::TradeCashback->value,
                TypeCoupon::FeeReduction->value,
            ]);

        $ticket = trim((string) ($filters['ticket'] ?? ''));
        if ($ticket !== '') {
            $query->whereRaw($this->usedRecordTicketSql() . ' like ?', [$this->like($ticket)]);
        }

        $count = (clone $query)->count('ucur.id');
        $data = $query
            ->select([
                'ucur.id',
                'ucur.biz_id',
                'ucur.event_data',
                DB::raw('ucur.created_at as used_at'),
            ])
            ->orderByDesc('ucur.id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->all();

        return [$data, $count];
    }

    public function forceVoidByCouponId(int $couponId): int
    {
        return $this->getQuery()
            ->where('coupon_id', $couponId)
            ->whereIn('status', UserCoupon::VOIDABLE_STATUSES)
            ->update([
                'status' => UserCoupon::STATUS_VOIDED,
            ]);
    }

    private function like(string $value): string
    {
        return sprintf('%%%s%%', addcslashes($value, '%_'));
    }

    private function buildLatestUsedRecordQuery(array $filters)
    {
        $query = DB::connection(DB_API)
            ->table('user_coupon_used_record as ucur_filter')
            ->selectRaw('MAX(ucur_filter.id) as id, ucur_filter.user_coupon_id')
            ->whereNotNull('ucur_filter.user_coupon_id')
            ->groupBy('ucur_filter.user_coupon_id');

        $bizId = trim((string) ($filters['biz_id'] ?? ''));
        if ($bizId !== '') {
            $query->where('ucur_filter.biz_id', 'like', $this->like($bizId));
        }

        if (($filters['trade_account_type'] ?? null) !== null && $filters['trade_account_type'] !== '') {
            $query->leftJoin('trade_account as ta_filter', function ($join): void {
                $join->on('ta_filter.trade_account_id', '=', 'ucur_filter.trade_account_id')
                    ->on('ta_filter.user_code', '=', 'ucur_filter.user_code');
            })->where('ta_filter.trade_account_type', (int) $filters['trade_account_type']);
        }

        $usedStartAt = trim((string) ($filters['used_start_at'] ?? ''));
        $usedEndAt = trim((string) ($filters['used_end_at'] ?? ''));
        if ($usedStartAt !== '' || $usedEndAt !== '') {
            $this->appendUsedAtFilter($query, $usedStartAt, $usedEndAt, 'ucur_filter');
        }

        return $query;
    }

    private function hasUsedRecordFilter(array $filters): bool
    {
        return trim((string) ($filters['biz_id'] ?? '')) !== ''
            || (($filters['trade_account_type'] ?? null) !== null && $filters['trade_account_type'] !== '')
            || trim((string) ($filters['used_start_at'] ?? '')) !== ''
            || trim((string) ($filters['used_end_at'] ?? '')) !== '';
    }

    private function appendCouponKeywordFilter(Builder $query, string $like): void
    {
        $query->where(function (Builder $builder) use ($like): void {
            $this->appendNameInternalCondition($builder, $like);

            foreach ($this->searchLanguages() as $lang) {
                $this->appendNameExternalCondition($builder, $like, $lang, 'or');
            }
        });
    }

    private function appendNameInternalFilter(Builder $query, string $like): void
    {
        $query->where(function (Builder $builder) use ($like): void {
            $this->appendNameInternalCondition($builder, $like);
        });
    }

    private function appendNameExternalFilter(Builder $query, string $like): void
    {
        $query->where(function (Builder $builder) use ($like): void {
            foreach ($this->searchLanguages() as $lang) {
                $this->appendNameExternalCondition($builder, $like, $lang, 'or');
            }
        });
    }

    private function appendNameInternalCondition(Builder $query, string $like): void
    {
        $query->whereRaw($this->nameInternalSelectSql() . ' like ?', [$like]);
    }

    private function appendNameExternalCondition(Builder $query, string $like, string $lang, string $boolean = 'and'): void
    {
        $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
        $query->{$method}($this->nameExternalTextSql($lang) . ' like ?', [$like]);
    }

    private function appendExpiredStatusFilter($query, int $expiredStatus): void
    {
        if ($expiredStatus === Coupon::EXPIRED_STATUS_EXPIRED) {
            $query->where('uc.status', '=', UserCoupon::STATUS_EXPIRED);

            return;
        }

        $query->where('uc.status', '<>', UserCoupon::STATUS_EXPIRED);
    }

    private function appendRedeemStatusFilter(Builder $query, int $redeemStatus): void
    {
        if ($redeemStatus === $this->redeemStatusCodeByIndex(1)) {
            $query->whereIn('uc.coupon_type', [
                TypeCoupon::TradeCashback->value,
                TypeCoupon::FeeReduction->value,
            ])
                ->where('uc.used_amount', '>', 0)
                ->whereColumn('uc.used_amount', '<', 'uc.total_amount');

            return;
        }

        if ($redeemStatus === $this->redeemStatusCodeByIndex(2)) {
            $query->where(function (Builder $builder): void {
                $builder->where(function (Builder $lossRecover): void {
                    $lossRecover->where('uc.coupon_type', TypeCoupon::LossRecover->value)
                        ->where('uc.used_amount', '>', 0);
                })->orWhere(function (Builder $amountBased): void {
                    $amountBased->whereIn('uc.coupon_type', [
                        TypeCoupon::TradeCashback->value,
                        TypeCoupon::FeeReduction->value,
                    ])
                        ->where('uc.used_amount', '>', 0)
                        ->whereColumn('uc.used_amount', '>=', 'uc.total_amount');
                });
            });
        }
    }

    private function appendRedeemedFilter(Builder $query): void
    {
        $query->where('uc.used_amount', '>', 0);
    }

    private function redeemStatusCodeByIndex(int $index): int
    {
        $codes = array_keys($this->redeemStatusLabels());

        return (int) ($codes[$index] ?? $index);
    }

    private function redeemStatusLabels(): array
    {
        $labels = trans('coupon.redeem_result');
        if (! is_array($labels)) {
            return [];
        }

        unset($labels['unknown']);

        return $labels;
    }

    private function appendUsedAtFilter($query, string $startAt, string $endAt, string $tableAlias = 'ucur'): void
    {
        $column = $tableAlias . '.created_at';

        if ($startAt !== '' && $endAt !== '') {
            $query->whereBetween($column, [$startAt, $endAt]);

            return;
        }

        if ($startAt !== '') {
            $query->where($column, '>=', $startAt);
        }

        if ($endAt !== '') {
            $query->where($column, '<=', $endAt);
        }
    }

    private function nameInternalSelectSql(): string
    {
        return "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(uc.coupon_config, '$.\"name_internal\"')), c.name_internal)";
    }

    private function nameExternalSelectSql(): string
    {
        return "COALESCE(JSON_EXTRACT(uc.coupon_config, '$.\"name_external\"'), c.name_external)";
    }

    private function nameExternalTextSql(string $lang): string
    {
        $escapedLang = str_replace(['\\', '"'], ['\\\\', '\\"'], $lang);

        return "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(uc.coupon_config, '$.\"name_external\".\"{$escapedLang}\"')), JSON_UNQUOTE(JSON_EXTRACT(c.name_external, '$.\"{$escapedLang}\"')))";
    }

    private function usedRecordTicketSql(): string
    {
        return "JSON_UNQUOTE(JSON_EXTRACT(ucur.event_data, '$.\"ticket\"'))";
    }

    private function searchLanguages(): array
    {
        $languages = array_keys(config('lang.languages', []));
        if (! empty($languages)) {
            return $languages;
        }

        return [config('app.locale', 'en_US')];
    }


    /**
     * 查询指定用户列表中已持有有效赠金（通用/限制型赠金券，同一赠金池）的用户ID集合。
     * 未作废、未过期且未用尽的赠金券视为已有赠金；赠金券用尽后，以关联
     * user_credit_record 判断赠金是否仍有剩余（grant_amount > deduct_amount）且在有效期内，
     * 已全额扣减（用完）的赠金不拦截重复发放。
     *
     * @param  array<int, int>  $userCodes
     * @return array<int, int>  已有赠金的 user_code 列表
     */
    public function findUserCodesWithExistingCreditBonus(array $userCodes): array
    {
        if ($userCodes === []) {
            return [];
        }

        return $this->getQuery()
            ->whereIn('user_code', $userCodes)
            ->whereIn('coupon_type', Coupon::creditBonusGroupTypeValues())
            ->where(function (Builder $query): void {
                $query->whereNotIn('user_coupon.status', [
                    UserCoupon::STATUS_UNUSABLE,
                    UserCoupon::STATUS_EXPIRED,
                    UserCoupon::STATUS_VOIDED,
                ])->orWhereExists(function (QueryBuilder $creditRecordQuery): void {
                    $creditRecordQuery->from('user_credit_record')
                        ->where('user_credit_record.biz_type', CreditBizType::Coupon->value)
                        ->whereColumn('user_credit_record.biz_id', 'user_coupon.id')
                        ->whereColumn('user_credit_record.user_code', 'user_coupon.user_code')
                        ->whereColumn('user_credit_record.grant_amount', '>', 'user_credit_record.deduct_amount')
                        ->where(function (QueryBuilder $expiryQuery): void {
                            $expiryQuery->whereNull('user_credit_record.end_at')
                                ->orWhere('user_credit_record.end_at', '>', now()->toDateTimeString());
                        });
                });
            })
            ->pluck('user_code')
            ->map(static fn (mixed $code): int => (int) $code)
            ->unique()
            ->values()
            ->all();
    }
}
