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
            $table->dropColumn('set_affiliate_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_BM)->table('role', function (Blueprint $table) {
            $table->tinyInteger('set_affiliate_status')->unsigned()->default(0)->comment('设置归属关系状态,0:不设置,1:设置')->after('status');
        });
    }
};
