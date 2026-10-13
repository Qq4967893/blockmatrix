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
        Schema::connection(DB_BM)->table('admin_user', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('admin_user', function (Blueprint $table) {
            $table->string('username', 100)->charset('utf8mb4')->default('')->comment('昵称')->after('login_name');
        });
    }
};
