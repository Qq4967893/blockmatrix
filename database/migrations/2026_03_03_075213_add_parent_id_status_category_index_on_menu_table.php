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
        Schema::connection(DB_BM)->table('menu', function (Blueprint $table) {
            $table->index(['parent_id', 'status', 'category'], 'idx_parent_id_status_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('menu', function (Blueprint $table) {
            $table->dropIndex('idx_parent_id_status_category');
        });
    }
};
