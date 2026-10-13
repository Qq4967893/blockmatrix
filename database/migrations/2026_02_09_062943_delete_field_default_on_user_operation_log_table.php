<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection(DB_BM)->table('user_operation_log', function (Blueprint $table) {
            $table->tinyInteger('target_type')->comment('内容类型：1:白名单,2:黑名单,3:layout模版,4:广告条目,5:广告记录,6:品种设置,7:Prop费用规则,8:Certified比赛,9:出金风控审核,10:出金财务审核,11:消息模版,12:用户基础资料,13:币种点差配置')->change();
            $table->tinyInteger('action_type')->comment('操作状态，1：添加，2：删除，3：修改')->change();
            $table->bigInteger('operator_id')->unsigned()->comment('后台操作人')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('user_operation_log', function (Blueprint $table) {
            $table->tinyInteger('target_type')->default(0)->comment('内容类型：1:白名单,2:黑名单,3:layout模版,4:广告条目,5:广告记录,6:品种设置,7:Prop费用规则,8:Certified比赛,9:出金风控审核,10:出金财务审核,11:消息模版,12:用户基础资料,13:币种点差配置')->change();
            $table->tinyInteger('action_type')->default(1)->comment('操作状态，1：添加，2：删除，3：修改')->change();
            $table->bigInteger('operator_id')->unsigned()->default(0)->comment('后台操作人')->change();
        });
    }
};
