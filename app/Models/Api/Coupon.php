<?php

namespace App\Models\Api;

use Illuminate\Database\Eloquent\Builder;
use ReflectionClass;
use Scaffold\Framework\Enums\TypeCoupon;
use Scaffold\Framework\Models\Api\Coupon as BaseCoupon;

class Coupon extends BaseCoupon
{
    public const int EXPIRED_STATUS_NOT_EXPIRED = 0;

    public const int EXPIRED_STATUS_EXPIRED = 1;

    /**
     * 赠金券虚拟分组码，仅用于 options 接口的前端交互，不会被 coupon_type 校验接受，永不入库。
     */
    public const int COUPON_TYPE_CREDIT_BONUS_GROUP = 0;

    /**
     * 聚合进赠金券分组的真实券型；后续新增限制型券种时往这里追加即可。
     *
     * @var array<int, TypeCoupon>
     */
    public const array CREDIT_BONUS_GROUP_TYPES = [
        TypeCoupon::CreditBonus,
        TypeCoupon::RestrictedCreditBonus,
    ];

    /**
     * 限制型赠金券必填的 config 字段；scaffold 的 required_variables 保持为空，
     * 券型维度的必填约定由应用层维护，options 输出与保存校验共用。
     *
     * @var array<int, string>
     */
    public const array RESTRICTED_CREDIT_BONUS_REQUIRED_CONFIG_FIELDS = ['restricted_max_leverage', 'restricted_source'];

    /**
     * 状态定义：
     * 0=未启用，1=启用，2=作废，3=停用
     */
    public const array STATUS_UPDATE_OPTIONS = [
        self::STATUS_ENABLED,
        self::STATUS_VOIDED,
        self::STATUS_STOPPED,
    ];

    /**
     * 仅未启用状态允许修改基础信息。
     */
    public const array EDITABLE_STATUSES = [
        self::STATUS_DISABLED,
    ];

    /**
     * 作废前允许的当前状态：未启用 / 启用。
     */
    public const array VOIDABLE_STATUSES = [
        self::STATUS_DISABLED,
        self::STATUS_ENABLED,
    ];

    protected $casts = [
        'coupon_type' => TypeCoupon::class,
        'name_external' => 'array',
        'description' => 'array',
        'config' => 'array',
        'active_config' => 'array',
    ];

    /**
     * @return array<int, int|string>
     */
    public static function activeUnitTypes(): array
    {
        $types = [];

        foreach ((new ReflectionClass(TypeCoupon::class))->getConstants() as $name => $value) {
            if (! str_starts_with($name, 'ACTIVE_UNIT_') || (! is_int($value) && ! is_string($value))) {
                continue;
            }

            $types[] = $value;
        }

        return array_values(array_unique($types, SORT_REGULAR));
    }

    /**
     * 券类型文案：项目内 coupon.type.{value} 覆盖优先（如 5=限制型赠金券），无覆盖时回退 scaffold 翻译。
     */
    public static function typeLabel(int|TypeCoupon $type): string
    {
        $couponType = $type instanceof TypeCoupon ? $type : TypeCoupon::tryFrom($type);
        if ($couponType === null) {
            return '';
        }

        $localKey = sprintf('coupon.type.%d', $couponType->value);
        $local = trans($localKey);
        if ($local !== $localKey) {
            return (string) $local;
        }

        $key = sprintf('scaffold::type_coupon.%d.description', $couponType->value);
        $label = trans($key);

        return $label === $key ? '' : (string) $label;
    }

    /**
     * config 字段是否必填：scaffold required_variables 之外的应用层约束。
     */
    public static function isConfigFieldRequired(int $couponType, string $field): bool
    {
        return $couponType === TypeCoupon::RestrictedCreditBonus->value
            && in_array($field, self::RESTRICTED_CREDIT_BONUS_REQUIRED_CONFIG_FIELDS, true);
    }

    /**
     * 券型是否属于赠金券分组：通用与限制型共用同一赠金池，发放时互斥。
     */
    public static function isCreditBonusGroupType(TypeCoupon $type): bool
    {
        return in_array($type, self::CREDIT_BONUS_GROUP_TYPES, true);
    }

    /**
     * @return array<int, int> 赠金券分组对应的 coupon_type 值
     */
    public static function creditBonusGroupTypeValues(): array
    {
        return array_map(static fn (TypeCoupon $type): int => $type->value, self::CREDIT_BONUS_GROUP_TYPES);
    }

    /**
     * 列表筛选：赠金券虚拟分组码映射为分组成员 IN 查询，其余券型精确匹配。
     */
    public static function applyCouponTypeFilter(Builder $query, string $column, int $couponType): void
    {
        if ($couponType === self::COUPON_TYPE_CREDIT_BONUS_GROUP) {
            $query->whereIn($column, array_map(
                static fn (TypeCoupon $type): int => $type->value,
                self::CREDIT_BONUS_GROUP_TYPES
            ));

            return;
        }

        $query->where($column, $couponType);
    }
}
