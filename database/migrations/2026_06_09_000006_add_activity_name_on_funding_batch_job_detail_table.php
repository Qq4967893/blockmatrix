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
        Schema::connection(DB_BM)->table('funding_batch_job_detail', function (Blueprint $table) {
            $table->string('activity_name', 100)->default('')->comment('活动名称')->after('error_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('funding_batch_job_detail', function (Blueprint $table) {
            $table->dropColumn('activity_name');
        });
    }
};
