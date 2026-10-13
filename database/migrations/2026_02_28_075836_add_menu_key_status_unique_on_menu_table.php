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
            $table->unique(['menu_key', 'status'], 'uniq_menu_key_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('menu', function (Blueprint $table) {
            $table->dropUnique('uniq_menu_key_status');
        });
    }
};
