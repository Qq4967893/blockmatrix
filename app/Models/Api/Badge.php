<?php

namespace App\Models\Api;

use App\Casts\LocalizedImageCast;
use App\Constants\FieldType;
use Scaffold\Framework\Enums\TypeBadgeEvent;
use Scaffold\Framework\Models\Api\Badge as BaseBadge;

class Badge extends BaseBadge
{
    public const TYPE_DATETIME = 1;

    public const TYPE_TEXT = 2;

    public const STATUS_DISABLED = 0;

    public const STATUS_ENABLED = 1;

    public const EVENT_KEY_MASTER_LEVEL_CHANGED = 'master_level_changed';

    private const REQUIRED_EVENT_PARAMS = [
        'close_trade' => ['trade_account_type'],
        'deposited' => ['amount'],
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'event_params' => 'array',
        'icon' => LocalizedImageCast::class,
    ];

    /**
     * 获取状态文案
     */
    public static function statusLabel(int $status): string
    {
        $key = sprintf('badge.status.%s', $status);
        $label = trans($key);

        return $label === $key ? trans('badge.status.unknown') : $label;
    }

    /**
     * 获取事件文案
     */
    public static function eventLabel(string $eventKey): string
    {
        $key = sprintf('badge.event.%s', $eventKey);
        $label = trans($key);
        if ($label !== $key) {
            return $label;
        }

        $scaffoldKey = sprintf('scaffold::type_badge_event.%s.description', $eventKey);
        $label = trans($scaffoldKey);

        return $label === $scaffoldKey ? trans('badge.event.unknown') : $label;
    }

    /**
     * @return array<int, array{
     *     key:string,
     *     name:string,
     *     event_params:array<int, array{
     *         type:string,
     *         param_key:string,
     *         param_name:string,
     *         is_required:bool,
     *         is_select_required:bool,
     *         options?:array<int, array{value:int,label:string}>
     *     }>
     * }>
     */
    public static function eventOptions(): array
    {
        return array_map(function (array $detail): array {
            $key = (string) ($detail['value'] ?? '');
            $labelKey = sprintf('scaffold::type_badge_event.%s.description', $key);
            $label = $detail['description'] ?? $key;

            return [
                'key' => $key,
                'name' => $label === $labelKey ? $key : $label,
                'event_params' => self::buildDefaultParamsFromVariables(
                    $key,
                    $detail['variable'] ?? []
                ),
            ];
        }, TypeBadgeEvent::details());
    }

    /**
     * @param  array<int, array{variable?:string,description?:string}>  $variables
     * @return array<int, array<string, mixed>>
     */
    private static function buildDefaultParamsFromVariables(string $eventKey, array $variables): array
    {
        $params = [];

        foreach ($variables as $item) {
            if (! is_array($item)) {
                continue;
            }

            $paramKey = trim((string) ($item['variable'] ?? ''), '{}');
            if ($paramKey === '') {
                continue;
            }

            $labelKey = sprintf(
                'scaffold::type_badge_event.%s.variables.%s',
                $eventKey,
                $paramKey
            );
            $label = $item['description'] ?? $paramKey;
            $param = [
                'type' => (string) self::detectParamType($paramKey),
                'param_key' => $paramKey,
                'param_name' => $label === $labelKey ? $paramKey : $label,
                'is_required' => in_array(
                    $paramKey,
                    self::REQUIRED_EVENT_PARAMS[$eventKey] ?? [],
                    true
                ),
                'is_select_required' => $paramKey === 'trade_account_type',
            ];

            $options = is_array($item['options'] ?? null) ? $item['options'] : [];
            if ($options !== []) {
                $param['options'] = $options;
            }

            $params[] = $param;
        }

        return $params;
    }

    private static function detectParamType(string $paramKey): int
    {
        if ($paramKey === 'trade_account_type') {
            return FieldType::SELECT;
        }

        return str_contains($paramKey, '_at')
            ? FieldType::DATETIME
            : FieldType::TEXT;
    }
}
