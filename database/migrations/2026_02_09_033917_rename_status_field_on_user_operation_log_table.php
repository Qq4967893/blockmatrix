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
            $table->renameColumn('status', 'action_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('user_operation_log', function (Blueprint $table) {
            $table->renameColumn('action_type', 'status');
        });
    }
};
