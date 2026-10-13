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
        Schema::connection(DB_BM)->create('funding_batch_job_detail', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('job_id')->comment('批次任务ID');
            $table->unsignedBigInteger('user_code')->default(0)->comment('用户ID');
            $table->decimal('amount', 18, 6)->unsigned()->default('0.000000')->comment('金额');
            $table->timestamp('biz_at')->nullable()->default(null)->comment('业务时间/费用期间');
            $table->string('reason', 255)->default('')->comment('原因描述');
            $table->unsignedTinyInteger('status')->default(0)->comment('状态: 0=待执行,1=执行中,2=成功,3=执行失败,4=导入无效');
            $table->string('error_message', 255)->default('')->comment('失败原因');
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('更新时间');

            $table->index('job_id', 'idx_job_id');
            $table->index(['job_id', 'status'], 'idx_job_id_status');
            $table->index('user_code', 'idx_user_code');

            $table->comment('批量资金任务明细表');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->dropIfExists('funding_batch_job_detail');
    }
};
