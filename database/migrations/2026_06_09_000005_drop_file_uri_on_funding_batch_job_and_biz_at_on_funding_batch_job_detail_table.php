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
        Schema::connection(DB_BM)->table('funding_batch_job', function (Blueprint $table) {
            $table->dropColumn('file_uri');
        });

        Schema::connection(DB_BM)->table('funding_batch_job_detail', function (Blueprint $table) {
            $table->dropColumn('biz_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('funding_batch_job', function (Blueprint $table) {
            $table->string('file_uri', 255)->nullable()->comment('导入文件地址')->after('file_name');
        });

        Schema::connection(DB_BM)->table('funding_batch_job_detail', function (Blueprint $table) {
            $table->timestamp('biz_at')->nullable()->default(null)->comment('业务时间/费用期间')->after('amount');
        });
    }
};
