<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Api\MessageSystem as BaseMessage;

class SystemMessage extends BaseMessage
{
    public const TYPE_ALL = 0; //全部
    public const TYPE_ASSIGN = 1; //指定
}
