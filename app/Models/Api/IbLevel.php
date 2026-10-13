<?php

namespace App\Models\Api;

use Scaffold\Framework\Enums\TypeIbLevelStatus;
use Scaffold\Framework\Models\Api\IbLevel as ScaffoldIbLevel;

class IbLevel extends ScaffoldIbLevel
{
    public const STATUS_ACTIVE = TypeIbLevelStatus::Enabled->value;

    public const ROOT_LEVEL_POS = 1;

    public const IS_ROOT_YES = 1;
}
