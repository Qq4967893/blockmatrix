<?php

namespace App\Repositories\Api;

use App\Models\Api\Coupon;
use App\Models\Api\UserCreditRecord;
use Scaffold\Framework\Enums\CreditBizType;
use Scaffold\Framework\Enums\TypeCoupon;
use Scaffold\Framework\Enums\TypeCreditLedger;
use Scaffold\Framework\Models\Api\UserCreditLedger;
use Scaffold\Framework\Repositories\Repository;

class UserCreditRecordRepository extends Repository
{
    public function __construct(UserCreditRecord $model)
    {
        parent::__construct($model);
    }

    /**
     * @return array{0: int, 1: array<int, UserCreditRecord>}
     */
    public function getCreditBonusDetailList(int $userCouponId, int $limit, int $offset): array
    {
        $query = $this->getQuery()
            ->join('user_coupon as uc', 'uc.id', '=', 'user_credit_record.biz_id')
            ->where('uc.id', $userCouponId)
            ->whereIn('uc.coupon_type', $this->creditBonusTypeValues())
            ->where('user_credit_record.biz_type', CreditBizType::Coupon->value);

        $count = (clone $query)->count('user_credit_record.id');
        $records = $query
            ->select('user_credit_record.*')
            ->orderByDesc('user_credit_record.id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->all();

        return [$count, $records];
    }

    /**
     * 赠金券分组的真实券型值。
     *
     * @return array<int, int>
     */
    private function creditBonusTypeValues(): array
    {
        return array_map(
            fn (TypeCoupon $type): int => $type->value,
            Coupon::CREDIT_BONUS_GROUP_TYPES
        );
    }

    public function latestByUserCode(int $userCode): ?UserCreditRecord
    {
        return $this->getQuery()
            ->where('user_code', $userCode)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * 历史数据统计全部记录；当前数据只统计未过期记录。
     *
     * @return array{total_bonus_amount:float,historical_unlocked_amount:float,current_bonus_amount:float,current_unlocked_amount:float}
     */
    public function summarizeByUserCode(int $userCode): array
    {
        $now = now()->format('Y-m-d H:i:s');

        $unlockedSub = UserCreditLedger::query()
            ->selectRaw('user_credit_record_id, COALESCE(SUM(amount), 0) AS unlocked_amount')
            ->where('type', TypeCreditLedger::Unlock->value)
            ->groupBy('user_credit_record_id');

        $summary = $this->getQuery()
            ->leftJoinSub($unlockedSub, 'ul', 'ul.user_credit_record_id', '=', 'user_credit_record.id')
            ->where('user_credit_record.user_code', $userCode)
            ->selectRaw('COALESCE(SUM(grant_amount), 0) as total_bonus_amount')
            ->selectRaw('COALESCE(SUM(ul.unlocked_amount), 0) as historical_unlocked_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN end_at IS NULL OR end_at > ? THEN grant_amount ELSE 0 END), 0) as current_bonus_amount', [$now])
            ->selectRaw('COALESCE(SUM(CASE WHEN end_at IS NULL OR end_at > ? THEN ul.unlocked_amount ELSE 0 END), 0) as current_unlocked_amount', [$now])
            ->first();

        return [
            'total_bonus_amount' => (float) ($summary->total_bonus_amount ?? 0),
            'historical_unlocked_amount' => (float) ($summary->historical_unlocked_amount ?? 0),
            'current_bonus_amount' => (float) ($summary->current_bonus_amount ?? 0),
            'current_unlocked_amount' => (float) ($summary->current_unlocked_amount ?? 0),
        ];
    }
}
