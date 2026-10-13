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
        Schema::connection(DB_BM)->rename('menu_url', 'menu_endpoint');

        Schema::connection(DB_BM)->table('menu_endpoint', function (Blueprint $table) {
            $table->renameColumn('server_url', 'api_path');
            $table->dropColumn('web_url');
            $table->comment('菜单接口关联表');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('menu_endpoint', function (Blueprint $table) {
            $table->string('web_url', 64)->nullable()->default('')->after('menu_id')->comment('菜单地址');
            $table->renameColumn('api_path', 'server_url');
            $table->comment('菜单地址对应接口地址');
        });

        Schema::connection(DB_BM)->rename('menu_endpoint', 'menu_url');
    }
};
