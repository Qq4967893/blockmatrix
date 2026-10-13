<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection(DB_BM)->rename('funding_batch_job', 'funding_operation');
        Schema::connection(DB_BM)->rename('funding_batch_job_detail', 'funding_operation_record');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->rename('funding_operation', 'funding_batch_job');
        Schema::connection(DB_BM)->rename('funding_operation_record', 'funding_batch_job_detail');
    }
};
