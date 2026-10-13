<?php

namespace App\Models\Api;

/**
 * 跨模块金额/货币精度常量。
 *
 * 当多个 bounded context 需要共享同一精度策略时可从此处引用；
 * 也可在各个上下文中单独定义，避免过度耦合。
 */
final class Money
{
    /** 金额保留小数位 */
    public const AMOUNT_DECIMAL_PLACES = 6;

    /** 金额微单位换算系数 (10^6) */
    public const AMOUNT_MICRO_SCALE = 1000000;

    /** 金额零值字符串 */
    public const ZERO_AMOUNT = '0.000000';

    /** 默认平台币种 */
    public const DEFAULT_CURRENCY = 'USDT';

    /** 手动操作最大金额 */
    public const MAX_MANUAL_AMOUNT = '1000000';

    /** 统一日期时间格式 */
    public const DATETIME_FORMAT = 'Y-m-d H:i:s';
}
