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
            $table->unique('login_name', 'uniq_login_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('admin_user', function (Blueprint $table) {
            $table->dropUnique('uniq_login_name');
        });
    }
};
