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
        Schema::connection(DB_BM)->table('role', function (Blueprint $table) {
            $table->bigInteger('area_id')->unsigned()->nullable()->comment('角色所属大区')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('role', function (Blueprint $table) {
            $table->bigInteger('area_id')->unsigned()->default(0)->comment('角色所属大区')->change();
        });
    }
};
