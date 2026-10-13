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
        Schema::create('user_operate_record', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->unsignedBigInteger('operate_target_id')->comment('操作目标记录ID');
            $table->text('content')->comment('修改内容');
            $table->tinyInteger('type')->default(0)->comment('内容类型：1:白名单,2:黑名单,3:layout模版,4:广告条目,5:广告记录,6:品种设置,7:Prop费用规则,8:Certified比赛,9:出金风控审核,10:出金财务审核,11:消息模版,12:用户基础资料,13:币种点差配置');
            $table->tinyInteger('status')->default(1)->comment('操作状态，1：添加，2：删除，3：修改');
            $table->unsignedBigInteger('admin_user_id')->default(0)->comment('后台操作人');
            $table->timestamp('created_at')->useCurrent()->comment('操作记录时间');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('最后更新时间');

            $table->primary('id');

            $table->comment('用户操作日志');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_operate_record');
    }
};
