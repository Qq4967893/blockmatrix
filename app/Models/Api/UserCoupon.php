<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Api\UserCoupon as BaseUserCoupon;

class UserCoupon extends BaseUserCoupon
{
    public const string BIZ_TYPE_TRADE_ORDER = 'trade_order';

    /**
     * 作废优惠券活动时，允许被同步作废的用户优惠券状态。
     */
    public const array VOIDABLE_STATUSES = [
        self::STATUS_UNCLAIMED,
        self::STATUS_INACTIVE,
        self::STATUS_USABLE,
    ];

    protected $casts = [
        'name_external' => 'array',
        'event_data' => 'array',
    ];
}
