<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Api\MasterLevel as ScaffoldMasterLevel;

class MasterLevel extends ScaffoldMasterLevel
{
    public const int DEFAULT_LEVEL_POS = 1;

    public const int IS_INITIAL_NO = 0;

    public const int IS_INITIAL_YES = 1;

    public const int STATUS_DISABLED = 0;

    public const int STATUS_ENABLED = 1;
}
