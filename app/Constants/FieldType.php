<?php

namespace App\Constants;

/**
 * 字段 UI 类型常量，积分、优惠券等模块统一引用。
 */
class FieldType
{
    /** 无需额外输入 */
    public const int NONE = 0;

    /** 日期时间 */
    public const int DATETIME = 1;

    /** 文本框 */
    public const int TEXT = 2;

    /** 下拉框 */
    public const int SELECT = 3;

    /** 级联选择器 */
    public const int CASCADER = 4;

    /** HTML文本框 */
    public const int TEXTAREA = 5;

    /** 开关 */
    public const int SWITCH = 6;

    /** 数字输入框 */
    public const int NUMBER = 7;
}
