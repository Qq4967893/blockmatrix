<?php

namespace App\Models\Api;

use Scaffold\Framework\Enums\CreditBizType;
use Scaffold\Framework\Models\Api\UserCreditRecord as BaseUserCreditRecord;

class UserCreditRecord extends BaseUserCreditRecord
{
    /** status 字段口径：0=不可用，1=可用。 */
    public const int STATUS_UNAVAILABLE = 0;

    public const int STATUS_AVAILABLE = 1;

    public const string CURRENCY = 'USDT';

    protected $casts = [
        'biz_type' => CreditBizType::class,
        'unlock_config' => 'array',
        'end_at' => 'datetime',
    ];
}
