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
        Schema::create('role', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->default('')->comment('角色名称');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('上级id');
            $table->tinyInteger('status')->default(1)->comment('状态 删除：0,正常：1');
            $table->unsignedTinyInteger('set_affiliate_status')->default(0)->comment('设置归属关系状态,0:不设置,1:设置');
            $table->unsignedBigInteger('area_id')->default(0)->comment('角色所属大区');
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('最后更新时间');

            $table->comment('角色表');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role');
    }
};
