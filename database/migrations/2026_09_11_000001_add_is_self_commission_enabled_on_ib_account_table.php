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
        Schema::connection(DB_API)->table('ib_account', function (Blueprint $table): void {
            $table->boolean('is_self_commission_enabled')->default(false)->comment('是否开启自己交易反佣: 0=否, 1=是')->after('is_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_API)->table('ib_account', function (Blueprint $table): void {
            $table->dropColumn('is_self_commission_enabled');
        });
    }
};
