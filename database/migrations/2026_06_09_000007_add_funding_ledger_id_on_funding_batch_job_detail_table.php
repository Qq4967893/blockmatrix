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
            $table->unsignedBigInteger('funding_ledger_id')->nullable()->default(null)->comment('资金流水id')->after('job_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('funding_batch_job_detail', function (Blueprint $table) {
            $table->dropColumn('funding_ledger_id');
        });
    }
};
