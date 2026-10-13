<?php

namespace App\Models\Api;

use App\Constants\FieldType;
use Scaffold\Framework\Enums\TypeGrantEvent;
use Scaffold\Framework\Models\Api\GrantRule as BaseGrantRule;

class GrantRule extends BaseGrantRule
{
    public const array SUPPORTED_TARGET_TYPES = [
        self::TARGET_COUPON,
        self::TARGET_BALANCE_FUNDING,
        self::TARGET_BALANCE_COMPETITION,
        self::TARGET_BALANCE_BONUSFUND,
        self::TARGET_POINTS_XP,
        self::TARGET_POINTS_DROPLET,
    ];

    public const int MAX_MANUAL_USER_COUNT = 500;

    public const int GRANT_METHOD_MANUAL = 1;

    public const int GRANT_METHOD_AUTO = 2;

    public const array STATUS_UPDATE_OPTIONS = [
        self::STATUS_ENABLED,
        self::STATUS_VOIDED,
    ];

    public const array VOIDABLE_STATUSES = [
        self::STATUS_DISABLED,
        self::STATUS_ENABLED,
    ];

    protected $casts = [
        'event_key' => TypeGrantEvent::class,
        'event_params' => 'array',
    ];

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
     *         options?:array<int, array{value:int,label:string}>,
     *         param_value:string
     *     }>,
     *     relation:array<int, string>,
     *     is_select_required?:bool
     * }>
     */
    public static function eventOptions(): array
    {
        $options = [];

        foreach (TypeGrantEvent::details() as $detail) {
            $key = (string) ($detail['value'] ?? '');
            if ($key === '' || $key === TypeGrantEvent::Manual->value) {
                continue;
            }

            $labelKey = sprintf('scaffold::type_grant_event.%s.description', $key);
            $label = $detail['description'] ?? $key;

            $relations = self::eventRelations($key);
            $option = [
                'key' => $key,
                'name' => $label === $labelKey ? $key : $label,
                'event_params' => self::buildDefaultParamsFromVariables($key, $detail['variable'] ?? []),
                'relation' => $relations === ['and'] ? [] : $relations,
            ];

            if ($key === TypeGrantEvent::InviteeRegistered->value) {
                $option['is_select_required'] = false;
            }

            $options[] = $option;
        }

        return $options;
    }

    /**
     * 入库时需要补齐默认值的事件参数键（数值、下拉参数缺省补 0）。
     *
     * @return array<string, int>
     */
    public static function eventParamDefaults(string $eventKey): array
    {
        $event = TypeGrantEvent::tryFrom($eventKey);
        if ($event === null || $event === TypeGrantEvent::Manual) {
            return [];
        }

        $defaults = [];
        foreach (TypeGrantEvent::details() as $detail) {
            if (($detail['value'] ?? '') !== $event->value) {
                continue;
            }

            foreach ($detail['variable'] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $paramKey = trim((string) ($item['variable'] ?? ''), '{}');
                if ($paramKey === '') {
                    continue;
                }

                $type = self::detectParamType($event->value, $paramKey);
                if (in_array($type, [FieldType::NUMBER, FieldType::SELECT], true)) {
                    $defaults[$paramKey] = 0;
                }
            }
        }

        return $defaults;
    }

    /**
     * 获取事件支持的原始 relation 选项（用于提交校验）。
     *
     * @return array<int, string>
     */
    public static function eventRelations(string $eventKey): array
    {
        $event = TypeGrantEvent::tryFrom($eventKey);
        if ($event === null) {
            return [];
        }

        $relations = TypeGrantEvent::parameterConfig($event)['relation'] ?? [];

        return is_array($relations) ? array_values($relations) : [];
    }

    /**
     * @param  array<int, array{variable?:string,description?:string}>  $variables
     * @return array<int, array{
     *     type:string,
     *     param_key:string,
     *     param_name:string,
     *     is_required:bool,
     *     is_select_required:bool,
     *     options?:array<int, array{value:int,label:string}>,
     *     param_value:string
     * }>
     */
    private static function buildDefaultParamsFromVariables(string $eventKey, array $variables): array
    {
        $params = [];

        foreach ($variables as $item) {
            if (! is_array($item)) {
                continue;
            }

            $variable = (string) ($item['variable'] ?? '');
            $paramKey = trim($variable, '{}');
            if ($paramKey === '') {
                continue;
            }

            $labelKey = sprintf('scaffold::type_grant_event.%s.variables.%s', $eventKey, $paramKey);
            $label = $item['description'] ?? $paramKey;
            $isRequired = self::isTradeAccountTypeParam($paramKey);

            $param = [
                'type' => (string) self::detectParamType($eventKey, $paramKey),
                'param_key' => $paramKey,
                'param_name' => $label === $labelKey ? $paramKey : $label,
                'is_required' => $isRequired,
                'is_select_required' => $isRequired,
                'param_value' => '',
            ];

            $options = is_array($item['options'] ?? null) ? $item['options'] : [];
            if ($options !== []) {
                $param['options'] = $options;
            }

            $params[] = $param;
        }

        return $params;
    }

    /** 仅交易账户类型参数必填且必须选择 */
    private static function isTradeAccountTypeParam(string $paramKey): bool
    {
        return $paramKey === 'trade_account_type';
    }

    private static function detectParamType(string $eventKey, string $paramKey): int
    {
        if ($paramKey === 'trade_account_type') {
            return FieldType::SELECT;
        }

        if ($eventKey === TypeGrantEvent::Lottery->value
            && in_array($paramKey, ['identifier', 'lottery_id'], true)
        ) {
            return FieldType::SELECT;
        }

        if ($eventKey === TypeGrantEvent::InviteeRegistered->value
            && $paramKey === 'inviter_user_code'
        ) {
            return FieldType::TEXTAREA;
        }

        if (in_array($paramKey, [
            'amount',
            'count',
            'position_size',
            'lots',
            'margin_order',
            'probability',
            'weight',
            'profit',
        ], true)) {
            return FieldType::NUMBER;
        }

        return str_contains($paramKey, '_at')
            ? FieldType::DATETIME
            : FieldType::TEXT;
    }
}
