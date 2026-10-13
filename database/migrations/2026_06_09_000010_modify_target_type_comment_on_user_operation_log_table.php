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
  COMMENT '内容类型: 1=白名单, 2=黑名单, 3=layout模版, 4=广告条目, 5=广告记录, 6=品种设置, 11=消息模版, 12=用户基础资料, 13=币种点差配置, 14=佣金规则, 15=上级代理, 16=IB等级, 18=验证码'
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
  COMMENT '内容类型：1:白名单,2:黑名单,3:layout模版,4:广告条目,5:广告记录,6:品种设置,7:Prop费用规则,8:Certified比赛,9:出金风控审核,10:出金财务审核,11:消息模版,12:用户基础资料,13:币种点差配置'
SQL);
    }
};
