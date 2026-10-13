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
            $table->bigInteger('parent_id')->unsigned()->nullable()->comment('上级id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('menu', function (Blueprint $table) {
            $table->bigInteger('parent_id')->unsigned()->default(0)->comment('上级id')->change();
        });
    }
};
