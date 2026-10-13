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
        Schema::connection(DB_BM)->create('funding_batch_job', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->tinyInteger('direction')->comment('资金方向: 1=发放, -1=扣除');
            $table->unsignedTinyInteger('fund_type')->comment('资金类型');
            $table->char('currency', 6)->default('USDT')->comment('金额币种');
            $table->string('file_name', 255)->nullable()->comment('导入文件名');
            $table->string('file_uri', 255)->nullable()->comment('导入文件地址');
            $table->unsignedInteger('total_count')->default(0)->comment('总条数');
            $table->unsignedInteger('valid_count')->default(0)->comment('有效条数');
            $table->unsignedInteger('success_count')->default(0)->comment('成功条数');
            $table->unsignedInteger('fail_count')->default(0)->comment('失败条数');
            $table->decimal('total_amount', 18, 6)->unsigned()->default('0.000000')->comment('导入总金额');
            $table->decimal('success_amount', 18, 6)->unsigned()->default('0.000000')->comment('成功总金额');
            $table->unsignedTinyInteger('status')->default(0)->comment('状态: 0=待执行,1=执行中,2=部分成功,3=成功,4=失败,5=已取消');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->unsignedBigInteger('created_by')->comment('提交管理员');
            $table->timestamp('executed_at')->nullable()->default(null)->comment('开始执行时间');
            $table->timestamp('finished_at')->nullable()->default(null)->comment('完成时间');
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新时间');

            $table->index('status', 'idx_status');
            $table->index(['direction', 'fund_type'], 'idx_direction_fund_type');
            $table->index('created_by', 'idx_created_by');

            $table->comment('批量资金任务主表');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->dropIfExists('funding_batch_job');
    }
};
