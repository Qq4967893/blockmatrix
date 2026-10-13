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
            $table->index(['target_type', 'target_id'], 'idx_target_type_target_id');
            $table->index('created_at', 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('user_operation_log', function (Blueprint $table) {
            $table->dropIndex('idx_target_type_target_id');
            $table->dropIndex('idx_created_at');
        });
    }
};
