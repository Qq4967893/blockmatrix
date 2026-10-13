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
        Schema::create('menu', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->comment('菜单名称');
            $table->unsignedBigInteger('parent_id')->default(0)->comment('上级id');
            $table->tinyInteger('status')->default(1)->comment('状态');
            $table->integer('pos')->default(0)->comment('排序');
            $table->tinyInteger('type')->default(1)->comment('0:系统设置菜单, 1:功能菜单');
            $table->string('menu_key', 64)->comment('菜单标识');
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('最后更新时间');

            $table->comment('菜单表');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu');
    }
};
