<?php

namespace App\Models\Blockmatrix;

use Scaffold\Framework\Models\Model;

class UserOperationLog extends Model
{
    protected $connection = DB_BM;

    protected $table = 'user_operation_log';

    public $timestamps = false;

    protected $guarded = [];

    public const DEFAULT_TARGET_ID = 0;

    public const DEFAULT_CONTENT = '{}';

    public const ACTION_TYPE_ADD = 1;  // 添加

    public const ACTION_TYPE_DELETE = 2;  // 删除

    public const ACTION_TYPE_UPDATE = 3; // 修改

    // 操作类型
    public const TARGET_TYPE_WHITE = 1;  // 白名单

    public const TARGET_TYPE_BLACK = 2;  // 黑名单

    public const TARGET_TYPE_TEMPLATE_LAYOUT = 3;  // layout模版

    public const TARGET_TYPE_AD = 4;  // 广告位

    public const TARGET_TYPE_AD_RECORD = 5;  // 广告记录

    public const TARGET_TYPE_SOURCE = 6; // 品种设置

    public const TARGET_TYPE_TEMPLATE_NOTICE = 7;  // 消息模版

    public const TARGET_TYPE_USER_PROFILE = 8; // 用户基础资料

    public const TARGET_TYPE_CURRENCY_SPREAD = 9; // 币种点差配置

    public const TARGET_TYPE_IB_COMMISSION_RULE = 10; // 佣金规则变更

    public const TARGET_TYPE_IB_PARENT = 11; // 上级代理变更

    public const TARGET_TYPE_IB_LEVEL = 12; // IB等级变更

    public const TARGET_TYPE_CAPTCHA_QUERY = 13; // 验证码查询

    public const TARGET_TYPE_GRANT_RULE = 14; // 投递任务

    public const TARGET_TYPE_COUPON = 15; // 优惠券

    public const TARGET_TYPE_FUNDING_NETWORK_CONFIG = 16; // 出入金网络配置

    public const TARGET_TYPE_MASTER_APPLICATION = 17; // 高手申请

    public const TARGET_TYPE_ARTICLE = 18; // 文章

    public const TARGET_TYPE_COMMUNITY_ACTIVITY = 19; // 市场活动
}
