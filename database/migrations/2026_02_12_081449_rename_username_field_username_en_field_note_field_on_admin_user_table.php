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
            $table->renameColumn('username', 'login_name');
            $table->renameColumn('username_en', 'username');
            $table->renameColumn('note', 'description');
            $table->string('username',100)->charset('utf8mb4')->default('')->comment('昵称')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('admin_user', function (Blueprint $table) {
            $table->renameColumn('username', 'username_en');
            $table->renameColumn('login_name', 'username');
            $table->renameColumn('description', 'note');
            $table->string('username_en', 100)->charset('utf8mb4')->default('')->comment('用户名英文')->change();
        });
    }
};
