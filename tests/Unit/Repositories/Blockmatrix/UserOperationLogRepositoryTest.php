<?php

namespace Tests\Unit\Repositories\Blockmatrix;

use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserOperationLogRepositoryTest extends TestCase
{
    private UserOperationLogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::connection('sqlite')->create('user_operation_log', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('target_id');
            $table->unsignedTinyInteger('target_type');
            $table->unsignedTinyInteger('action_type');
            $table->unsignedBigInteger('operator_id')->default(0);
            $table->string('content')->nullable();
        });
        Schema::connection('sqlite')->create('admin_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('login_name')->nullable();
        });

        $model = new class extends UserOperationLog
        {
            protected $connection = 'sqlite';
        };
        $this->repository = new UserOperationLogRepository($model);
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlite')->dropIfExists('user_operation_log');
        Schema::connection('sqlite')->dropIfExists('admin_user');

        parent::tearDown();
    }

    public function test_get_latest_operate_info_returns_only_latest_operator_per_target(): void
    {
        DB::connection('sqlite')->table('admin_user')->insert([
            ['id' => 1, 'login_name' => 'admin1'],
            ['id' => 2, 'login_name' => 'admin2'],
        ]);
        DB::connection('sqlite')->table('user_operation_log')->insert([
            ['id' => 1, 'target_id' => 101, 'target_type' => UserOperationLog::TARGET_TYPE_COMMUNITY_ACTIVITY, 'action_type' => UserOperationLog::ACTION_TYPE_ADD, 'operator_id' => 1],
            ['id' => 2, 'target_id' => 101, 'target_type' => UserOperationLog::TARGET_TYPE_COMMUNITY_ACTIVITY, 'action_type' => UserOperationLog::ACTION_TYPE_UPDATE, 'operator_id' => 2],
            ['id' => 3, 'target_id' => 102, 'target_type' => UserOperationLog::TARGET_TYPE_COMMUNITY_ACTIVITY, 'action_type' => UserOperationLog::ACTION_TYPE_ADD, 'operator_id' => 1],
            // 其他 target_type / 非 activity 日志不应影响结果
            ['id' => 4, 'target_id' => 103, 'target_type' => UserOperationLog::TARGET_TYPE_ARTICLE, 'action_type' => UserOperationLog::ACTION_TYPE_ADD, 'operator_id' => 1],
        ]);

        $result = $this->repository->getLatestOperateInfo(UserOperationLog::TARGET_TYPE_COMMUNITY_ACTIVITY, [101, 102, 103, 0]);
        ksort($result);

        $this->assertSame([
            101 => ['operator_id' => 2, 'operator_name' => 'admin2'],
            102 => ['operator_id' => 1, 'operator_name' => 'admin1'],
        ], $result);
    }

    public function test_get_latest_operate_info_returns_empty_without_valid_target_ids(): void
    {
        $this->assertSame([], $this->repository->getLatestOperateInfo(UserOperationLog::TARGET_TYPE_COMMUNITY_ACTIVITY, [0, -1]));
    }
}
