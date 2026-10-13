<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Model;

class TemplateNotice extends Model
{
    protected $connection = DB_API;
    protected $table = 'template_notice';
    public $timestamps = false;

    /** 消息模板类型 */
    public const TYPE_ALL = 0;
    public const TYPE_EMAIL = 1;
    public const TYPE_IN_APP = 2;

    /** 事件类型 */
    public const EVENT_TYPE_ALL = 0;
    public const EVENT_TYPE_SYSTEM = 1;
    public const EVENT_TYPE_TRADING = 2;

    /** 系统事件键前缀 */
    public const SYSTEM_EVENT_PREFIX = 'sys_';

    /**
     * @return int[]
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_EMAIL,
            self::TYPE_IN_APP,
        ];
    }

    /**
     * 判断事件键是否为系统事件。
     */
    public static function isSystemEvent(string $eventKey): bool
    {
        return str_starts_with($eventKey, self::SYSTEM_EVENT_PREFIX);
    }

    /**
     * 根据事件键返回事件类型。
     */
    public static function eventType(string $eventKey): int
    {
        return self::isSystemEvent($eventKey)
            ? self::EVENT_TYPE_SYSTEM
            : self::EVENT_TYPE_TRADING;
    }
}
