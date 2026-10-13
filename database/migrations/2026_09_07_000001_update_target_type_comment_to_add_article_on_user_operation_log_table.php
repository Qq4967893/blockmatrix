<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::connection(DB_BM)->statement(<<<'SQL'
ALTER TABLE `user_operation_log`
  MODIFY COLUMN `target_type` TINYINT NOT NULL
  COMMENT '内容类型: 1=白名单, 2=黑名单, 3=layout模版, 4=广告条目, 5=广告记录, 6=品种设置, 7=消息模版, 8=用户基础资料, 9=币种点差配置, 10=佣金规则, 11=上级代理, 12=IB等级, 13=验证码, 14=投递任务, 15=优惠券, 16=出入金网络配置, 17=高手申请审核, 18=文章'
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::connection(DB_BM)->statement(<<<'SQL'
ALTER TABLE `user_operation_log`
  MODIFY COLUMN `target_type` TINYINT NOT NULL
  COMMENT '内容类型: 1=白名单, 2=黑名单, 3=layout模版, 4=广告条目, 5=广告记录, 6=品种设置, 7=消息模版, 8=用户基础资料, 9=币种点差配置, 10=佣金规则, 11=上级代理, 12=IB等级, 13=验证码, 14=投递任务, 15=优惠券, 16=出入金网络配置, 17=高手申请审核'
SQL);
    }
};
