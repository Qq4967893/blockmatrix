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
        Schema::connection(DB_API)->table('community_activity', function (Blueprint $table): void {
            $table->timestamp('apply_start_at')->nullable()->comment('报名开始时间')->after('end_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(DB_API)->table('community_activity', function (Blueprint $table): void {
            $table->dropColumn('apply_start_at');
        });
    }
};
