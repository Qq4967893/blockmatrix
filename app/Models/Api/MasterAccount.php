<?php

namespace App\Models\Api;

class MasterAccount extends \Scaffold\Framework\Models\Api\MasterAccount
{
    public const int AMOUNT_DECIMAL_PLACES = 6;
    public const int RATE_DECIMAL_PLACES = 6;
    public const int OPERATION_PRIVATE = 5;
    public const int IS_MANUAL_AUTO    = 0;
    public const int IS_MANUAL_MANUAL  = 1;
    public const int IS_PRIVATE_PUBLIC = 0;
    public const int IS_PRIVATE_YES    = 1;
    public const int PROTECTION_STATUS_INACTIVE = 0;
    public const int PROTECTION_STATUS_ACTIVE = 1;
    public const int PROTECTION_STATUS_ENDED = 2;
}
