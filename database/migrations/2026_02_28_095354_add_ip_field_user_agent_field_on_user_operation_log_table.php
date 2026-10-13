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
            $table->string('ip', 100)->default('')->comment('ip地址')->after('operator_id');
            $table->string('user_agent', 100)->default('')->comment('客户端信息')->after('operator_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('user_operation_log', function (Blueprint $table) {
            $table->dropColumn('ip');
            $table->dropColumn('user_agent');
        });
    }
};
