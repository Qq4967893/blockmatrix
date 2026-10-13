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
            $table->string('user_agent', 255)->default('')->comment('客户端信息')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('user_operation_log', function (Blueprint $table) {
            $table->string('user_agent', 100)->default('')->comment('客户端信息')->change();
        });
    }
};
