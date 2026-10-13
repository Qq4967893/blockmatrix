<?php

namespace Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Scaffold TypeGrantEvent 的 lottery 事件选项会查询 api 库；
        // 测试统一改用内存 SQLite，避免依赖真实 MySQL。
        config()->set('database.connections.api', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        DB::purge(DB_API);

        Schema::connection(DB_API)->create('lottery', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('identifier');
            $table->string('name');
            $table->unsignedInteger('event_key')->nullable();
            $table->text('event_params')->nullable();
            $table->unsignedInteger('cost_type')->default(0);
            $table->decimal('cost_value', 18, 6)->default(0);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamps();
        });
    }
}
