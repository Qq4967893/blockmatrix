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
            $table->bigInteger('leader_id')->unsigned()->nullable()->comment('上级id')->change();
            $table->bigInteger('role_id')->unsigned()->nullable()->comment('角色id')->change();
            $table->bigInteger('level_id')->unsigned()->nullable()->comment('层级id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('admin_user', function (Blueprint $table) {
            $table->bigInteger('leader_id')->unsigned()->default(0)->comment('上级id')->change();
            $table->bigInteger('role_id')->unsigned()->default(0)->comment('角色id')->change();
            $table->bigInteger('level_id')->unsigned()->default(0)->comment('层级id')->change();
        });
    }
};
