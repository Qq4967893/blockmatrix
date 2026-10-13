<?php

namespace App\Models\Api;

use Scaffold\Framework\Models\Api\IbAccount as ScaffoldIbAccount;

class IbAccount extends ScaffoldIbAccount
{
    /** IB 佣金状态 */
    public const COMMISSION_STATUS_UNASSIGNED = 0;
    public const COMMISSION_STATUS_ASSIGNED = 1;

    /** 是否开启自己交易反佣 */
    public const SELF_COMMISSION_DISABLED = 0;
    public const SELF_COMMISSION_ENABLED = 1;

    /** IB 等级保护状态 */
    public const PROTECTION_STATUS_INACTIVE = 0;
    public const PROTECTION_STATUS_ACTIVE = 1;
    public const PROTECTION_STATUS_ENDED = 2;

    /** IB 用户类型标签 */
    public const USER_TYPE_PARTNER = 'Partner';
    public const USER_TYPE_SUB_PARTNER = 'Sub Partner';
    public const USER_TYPE_TRADER = 'Trader';

    /** IB ID 填充长度 */
    public const IB_ID_PAD_LENGTH = 6;
}
