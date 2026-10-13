<?php

namespace Tests\Unit\Services\Api;

use App\Models\Api\IbAccount;
use App\Models\Api\IbLevel;
use App\Repositories\Api\IbAccountRepository;
use App\Repositories\Api\IbApplicationRepository;
use App\Repositories\Api\IbLevelRepository;
use App\Repositories\Api\UserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Services\Api\IbAccountService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Scaffold\Framework\Services\IbLevelOperateService;
use Tests\TestCase;

class IbAccountServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_update_level_only_updates_protection_times_when_level_unchanged(): void
    {
        Auth::shouldReceive('id')->andReturn(1001);

        $connection = Mockery::mock();
        $connection->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static function (callable $callback): void {
                $callback();
            });
        DB::shouldReceive('connection')->with(DB_API)->once()->andReturn($connection);

        $level = new IbLevel([
            'id' => 3,
            'status' => 1,
            'name' => ['en_US' => 'Gold'],
        ]);
        $account = new IbAccount([
            'id' => 1,
            'user_code' => 10001,
            'level_id' => 3,
            'level_protection_started_at' => null,
            'level_protection_ended_at' => null,
        ]);
        $updated = new IbAccount([
            'id' => 1,
            'user_code' => 10001,
            'level_id' => 3,
            'level_protection_started_at' => '2026-08-01 10:20:30',
            'level_protection_ended_at' => null,
        ]);

        $userRepo = Mockery::mock(UserRepository::class);
        $applicationRepo = Mockery::mock(IbApplicationRepository::class);
        $accountRepo = Mockery::mock(IbAccountRepository::class);
        $levelRepo = Mockery::mock(IbLevelRepository::class);
        $levelOperateService = Mockery::mock(IbLevelOperateService::class);
        $logRepo = Mockery::mock(UserOperationLogRepository::class);

        $levelRepo->shouldReceive('findById')->with(3)->twice()->andReturn($level);
        $accountRepo->shouldReceive('findByIdForUpdate')->with(1)->once()->andReturn($account);
        $accountRepo->shouldReceive('updateAccount')
            ->once()
            ->with(
                1,
                Mockery::on(static function (array $payload): bool {
                    return $payload['level_id'] === 3
                        && $payload['level_protection_started_at'] === '2026-08-01 10:20:30'
                        && $payload['level_protection_ended_at'] === null;
                }),
                $account
            )
            ->andReturn($updated);
        $levelOperateService->shouldNotReceive('modify');
        $logRepo->shouldNotReceive('addRecord');

        $service = new IbAccountService(
            $userRepo,
            $applicationRepo,
            $accountRepo,
            $levelRepo,
            $levelOperateService,
            $logRepo
        );

        $service->updateLevel('000001', [], 3, '2026-08-01 10:20:30', null);
    }
}
