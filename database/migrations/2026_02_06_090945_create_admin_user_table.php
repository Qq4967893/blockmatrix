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
        Schema::create('admin_user', function (Blueprint $table) {
            $table->bigInteger('id')->autoIncrement();
            $table->string('username', 100)->comment('用户名');
            $table->string('username_en', 100)->default('')->comment('用户名英文');
            $table->string('email', 100)->nullable()->comment('邮箱');
            $table->string('phone', 45)->nullable()->comment('电话');
            $table->string('city', 255)->nullable()->comment('城市');
            $table->tinyInteger('status')->default(1)->comment('状态，0：已删除，1：正常');
            $table->string('password', 60)->comment('密码');
            $table->string('address', 255)->nullable()->comment('地址');
            $table->string('note', 512)->nullable()->comment('备注');
            $table->unsignedBigInteger('leader_id')->default(0)->comment('上级id');
            $table->unsignedBigInteger('role_id')->default(0)->comment('角色id');
            $table->unsignedBigInteger('level_id')->default(0)->comment('层级id');
            $table->timestamp('created_at')->useCurrent()->comment('创建时间');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()->comment('最后更新时间');

            $table->primary('id');
            $table->index('leader_id', 'index_leader');

            $table->comment('后台用户表');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_0900_ai_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_user');
    }
};
