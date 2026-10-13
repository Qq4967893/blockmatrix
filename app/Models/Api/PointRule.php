<?php

namespace App\Models\Api;

use App\Models\Point\PointRuleConstant;
use Scaffold\Framework\Models\Model;

class PointRule extends Model
{
    public const STATUS_PENDING  = -1;
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED  = 1;

    public const CYCLE_LIMIT_UNIQUE  = 1;
    public const CYCLE_LIMIT_LOOP    = 0;
    public const CYCLE_LIMIT_LIMITED = -1;

    public const TASK_TYPE_NONE     = 0;
    public const TASK_TYPE_NEW_USER = 1;
    public const TASK_TYPE_TRADE    = 2;
    public const TASK_TYPE_SOCIAL   = 3;

    public const POINTS_TYPE_XP      = 1;
    public const POINTS_TYPE_DROPLET = 2;

    public const EVENT_KEY_IB_LEVEL_UPGRADE = 'ib_level_upgrade';

    protected $connection = DB_API;
    protected $table      = 'point_rule';
    protected $guarded    = [];

    protected $casts = [
        'event_params'  => 'array',
        'name_external' => 'array',
        'description'   => 'array',
        'pop_up'        => 'array',
    ];

    /**
     * 获取周期标签
     *
     * @param int $cyclePeriod 周期值
     *
     * @return string
     */
    public static function cyclePeriodLabel($cyclePeriod)
    {
        $key         = sprintf('point_rule.cycle_period.%s', $cyclePeriod);
        $translation = trans($key);

        return $translation === $key ? trans('point_rule.cycle_period.unknown') : $translation;
    }

    /**
     * 获取状态标签
     *
     * @param int $status 状态值
     *
     * @return string
     */
    public static function statusLabel($status)
    {
        $key         = sprintf('point_rule.status.%s', $status);
        $translation = trans($key);

        return $translation === $key ? trans('point_rule.status.unknown') : $translation;
    }

    /**
     * 获取事件标签
     *
     * @param string $eventKey 事件key
     *
     * @return string
     */
    public static function eventKeyLabel($eventKey)
    {
        return PointRuleConstant::eventLabel($eventKey);
    }

    /**
     * 任务类型标签
     *
     * @param int $taskType task type code
     *
     * @return string
     */
    public static function taskLabel(int $taskType): string
    {
        $key         = sprintf('point_rule.task_type.%s', $taskType);
        $translation = trans($key);

        return $translation === $key ? trans('point_rule.task_type.0') : $translation;
    }

    /**
     * 积分类型标签
     *
     * @param int $pointsType points type code
     *
     * @return string
     */
    public static function pointsTypeLabel(int $pointsType): string
    {
        $key         = sprintf('point_rule.points_type.%s', $pointsType);
        $translation = trans($key);

        return $translation === $key ? trans('point_rule.points_type.unknown') : $translation;
    }

    /**
     * 获取循环次数标签
     *
     * @param int $cycleLimit 次数
     *
     * @return string
     */
    public static function cycleLimitLabel($cycleLimit)
    {
        $map = [
            self::CYCLE_LIMIT_UNIQUE  => 'unique',
            self::CYCLE_LIMIT_LOOP    => 'loop',
            self::CYCLE_LIMIT_LIMITED => 'limited',
        ];

        // 非预置值且大于 0 时，视为限次 N
        if (! isset($map[$cycleLimit]) && $cycleLimit > 0) {
            return sprintf('%s：%s', trans('point_rule.cycle_limit.limited'), $cycleLimit);
        }

        $key         = sprintf('point_rule.cycle_limit.%s', $map[$cycleLimit] ?? 'unknown');
        $translation = trans($key);

        return $translation === $key ? trans('point_rule.cycle_limit.unknown') : $translation;
    }
}
