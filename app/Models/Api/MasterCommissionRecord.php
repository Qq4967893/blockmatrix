<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Api\MasterCommissionRecord as ScaffoldMasterCommissionRecord;

class MasterCommissionRecord extends ScaffoldMasterCommissionRecord
{
    public const int STATUS_PENDING = 0;
    public const int STATUS_SETTLED = 1;
}
