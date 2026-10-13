<?php

namespace Tests\Unit\Services\Api;

use App\Models\Blockmatrix\UserOperationLog;
use App\Repositories\Api\UserRepository;
use App\Repositories\Blockmatrix\UserOperationLogRepository;
use App\Services\Api\CaptchaLookupService;
use App\Utils\BaseRedis;
use Illuminate\Redis\Connections\Connection;
use Mockery;
use PHPUnit\Framework\TestCase;

class CaptchaLookupServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_get_email_code_reads_backend_api_cache_and_records_log_when_code_exists(): void
    {
        $redisConnection = Mockery::mock(Connection::class);
        $baseRedis = Mockery::mock('alias:' . BaseRedis::class);
        $baseRedis->shouldReceive('getApiCacheRedis')
            ->once()
            ->andReturn($redisConnection);
        $redisConnection->shouldReceive('get')
            ->once()
            ->with('captcha:email:test@example.com')
            ->andReturn('123456');

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldReceive('findUserCodeByEmail')
            ->once()
            ->with('test@example.com')
            ->andReturn(10001);

        $logRepo = Mockery::mock(UserOperationLogRepository::class);
        $logRepo->shouldReceive('addRecord')
            ->once()
            ->with(
                10001,
                UserOperationLog::TARGET_TYPE_CAPTCHA_QUERY,
                Mockery::on(function (string $content): bool {
                    $decoded = json_decode($content, true);

                    return $decoded['reason'] === 'manual lookup'
                        && $decoded['email'] === 'test@example.com'
                        && $decoded['channel'] === 'email'
                        && $decoded['cache_key'] === 'api:cache:captcha:email:test@example.com';
                })
            )
            ->andReturn(true);

        $service = new CaptchaLookupService($userRepo, $logRepo);

        $result = $service->getEmailCode(' Test@Example.com ', 'manual lookup');

        $this->assertSame(['code' => '123456'], $result);
    }

    public function test_get_email_code_skips_logging_when_code_does_not_exist(): void
    {
        $redisConnection = Mockery::mock(Connection::class);
        $baseRedis = Mockery::mock('alias:' . BaseRedis::class);
        $baseRedis->shouldReceive('getApiCacheRedis')
            ->once()
            ->andReturn($redisConnection);
        $redisConnection->shouldReceive('get')
            ->once()
            ->with('captcha:email:missing@example.com')
            ->andReturn(null);

        $userRepo = Mockery::mock(UserRepository::class);
        $userRepo->shouldNotReceive('findUserCodeByEmail');

        $logRepo = Mockery::mock(UserOperationLogRepository::class);
        $logRepo->shouldNotReceive('addRecord');

        $service = new CaptchaLookupService($userRepo, $logRepo);

        $result = $service->getEmailCode('missing@example.com', 'manual lookup');

        $this->assertSame(['code' => ''], $result);
    }

    public function test_get_email_code_logs_maps_paginated_records(): void
    {
        $userRepo = Mockery::mock(UserRepository::class);
        $logRepo = Mockery::mock(UserOperationLogRepository::class);

        $logRepo->shouldReceive('getPaginatedLogs')
            ->once()
            ->with(
                [
                    'target_type' => UserOperationLog::TARGET_TYPE_CAPTCHA_QUERY,
                    'action_type' => UserOperationLog::ACTION_TYPE_ADD,
                    'user_code' => 10001,
                    'email' => 'test@example.com',
                ],
                20,
                0
            )
            ->andReturn([
                [
                    [
                        'target_id' => 10001,
                        'content' => json_encode([
                            'email' => 'test@example.com',
                            'reason' => 'manual lookup',
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => '2026-06-04 12:00:00',
                        'login_name' => 'admin',
                    ],
                ],
                1,
            ]);

        $service = new CaptchaLookupService($userRepo, $logRepo);

        $result = $service->getEmailCodeLogs([
            'email' => ' Test@Example.com ',
            'user_code' => 10001,
            'limit' => 20,
            'offset' => 0,
        ]);

        $this->assertSame([
            'data' => [
                [
                    'email' => 'test@example.com',
                    'reason' => 'manual lookup',
                    'user_code' => 10001,
                    'operator_name' => 'admin',
                    'operated_at' => '2026-06-04 12:00:00',
                ],
            ],
            'total_count' => 1,
        ], $result);
    }
}
