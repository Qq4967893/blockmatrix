<?php

namespace App\Models\Point;

use App\Constants\FieldType;
use Scaffold\Framework\Enums\TypePage;
use Scaffold\Framework\Enums\TypePointEvent;

class PointRuleConstant
{
    /** 跳转类型：无跳转 */
    public const JUMP_TYPE_NONE = -1;

    /** 跳转类型：内部模块ID */
    public const JUMP_TYPE_INTERNAL = 0;

    /** 跳转类型：外部链接 */
    public const JUMP_TYPE_EXTERNAL = 1;

    public const PARAM_TYPE_DATETIME = FieldType::DATETIME;
    public const PARAM_TYPE_TEXT     = FieldType::TEXT;
    public const PARAM_TYPE_SELECT   = FieldType::SELECT;
    public const PARAM_TYPE_CASCADER = FieldType::CASCADER;

    private const REQUIRED_EVENT_PARAMS = [
        'deposited'        => ['amount'],
        'open_trade'       => ['trade_account_type'],
        'close_trade'      => ['trade_account_type'],
        'first_open_trade' => ['trade_account_type'],
        'ib_invitee_valid_customer' => ['peoples'],
        'ib_team_position_size'     => ['team_position_size'],
    ];

    /**
     * 获取所有事件翻译
     *
     * @return array<string, string>
     */
    private static function eventTranslations()
    {
        $translations = [];
        foreach (self::eventDetails() as $detail) {
            $eventKey = $detail['value'] ?? '';
            if ($eventKey === '') {
                continue;
            }
            $labelKey = sprintf('scaffold::type_point_event.%s.description', $eventKey);
            $label    = $detail['description'] ?? $eventKey;
            $translations[$eventKey] = $label === $labelKey ? $eventKey : $label;
        }

        return is_array($translations) ? $translations : [];
    }

    /**
     * 获取事件描述
     *
     * @param string $eventKey 事件标识
     *
     * @return string
     */
    public static function eventLabel($eventKey)
    {
        $translations = self::eventTranslations();

        return $translations[$eventKey] ?? trans('point_rule.event_key.unknown');
    }

    /**
     * 获取有效的事件编码列表
     *
     * @return array<int, string>
     */
    public static function eventKeys()
    {
        return array_column(self::eventDetails(), 'value');
    }

    /**
     * 获取事件选项
     *
     * @return array<int, array{key:string,name:string,event_params:array<int, array{type:string,param_key:string,param_name:string,is_required:bool,is_select_required:bool,options?:array<int, array{value:int,label:string}>,param_value:string}>}> is_required=是否必填 is_select_required=是否必须选择
     */
    public static function eventOptions()
    {
        return array_map(function (array $detail) {
            $key      = $detail['value'] ?? '';
            $labelKey = sprintf('scaffold::type_point_event.%s.description', $key);
            $label    = $detail['description'] ?? $key;
            $variables = $detail['variable'] ?? [];

            return [
                'key'          => $key,
                'name'         => $label === $labelKey ? $key : $label,
                'event_params' => self::buildDefaultParamsFromVariables($key, $variables),
            ];
        }, self::eventDetails());
    }

    /**
     * 构建默认事件参数
     *
     * @param array $template
     *
     * @return array<int, array{type:string,param_key:string,param_name:string,is_required:bool,is_select_required:bool,options?:array<int, array{value:int,label:string}>,param_value:string}> is_required=是否必填 is_select_required=是否必须选择
     */
    private static function buildDefaultParamsFromVariables(string $eventKey, array $variables): array
    {
        $params = [];
        foreach ($variables as $item) {
            if (!is_array($item)) {
                continue;
            }
            $variable = (string)($item['variable'] ?? '');
            $paramKey = trim($variable, '{}');
            if ($paramKey === '') {
                continue;
            }
            $labelKey = sprintf('scaffold::type_point_event.%s.variables.%s', $eventKey, $paramKey);
            $label    = $item['description'] ?? $paramKey;
            $param = [
                'type'               => (string) self::detectParamType($paramKey),
                'param_key'          => $paramKey,
                'param_name'         => $label === $labelKey ? $paramKey : $label,
                'is_required'        => self::isRequiredEventParam($eventKey, $paramKey),
                'is_select_required' => self::isSelectRequiredEventParam($eventKey, $paramKey),
            ];

            $options = is_array($item['options'] ?? null) ? $item['options'] : [];
            if ($options !== []) {
                $param['options'] = $options;
            }

            $params[] = $param;
        }

        return $params;
    }

    /**
     * 入库时需要补齐默认值的事件参数键（trade_account_type 除外，其余缺省补 0）。
     *
     * @return array<string, int>
     */
    public static function eventParamDefaults(string $eventKey): array
    {
        $defaults = [];
        foreach (self::eventDetails() as $detail) {
            if (($detail['value'] ?? '') !== $eventKey) {
                continue;
            }

            foreach ($detail['variable'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $paramKey = trim((string) ($item['variable'] ?? ''), '{}');
                if ($paramKey === '' || $paramKey === 'trade_account_type') {
                    continue;
                }

                $defaults[$paramKey] = 0;
            }
        }

        return $defaults;
    }

    /**
     * 获取中间件事件详情
     *
     * @return array<int, array{value:string,description:string,variable:array}>
     */
    private static function eventDetails(): array
    {
        return TypePointEvent::details();
    }

    private static function detectParamType(string $paramKey): int
    {
        if ($paramKey === 'trade_account_type') {
            return self::PARAM_TYPE_SELECT;
        }

        return str_contains($paramKey, '_at')
            ? self::PARAM_TYPE_DATETIME
            : self::PARAM_TYPE_TEXT;
    }

    private static function isRequiredEventParam(string $eventKey, string $paramKey): bool
    {
        return in_array($paramKey, self::REQUIRED_EVENT_PARAMS[$eventKey] ?? [], true);
    }

    private static function isSelectRequiredEventParam(string $eventKey, string $paramKey): bool
    {
        return $paramKey === 'trade_account_type'
            && in_array($eventKey, ['first_open_trade', 'open_trade', 'close_trade'], true);
    }

    /**
     * 获取合法的内部模块ID列表
     *
     * @return array<int>
     */
    public static function internalModuleIds(): array
    {
        return array_map(
            static fn (TypePage $page): int => $page->value,
            TypePage::cases()
        );
    }
}
