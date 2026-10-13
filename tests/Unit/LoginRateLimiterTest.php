<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class LoginRateLimiterTest extends TestCase
{
    public function test_login_rate_limiter_builds_account_and_ip_limits(): void
    {
        $request = Request::create(
            '/api/auth/login',
            'POST',
            ['login_name' => 'AdminUser'],
            [],
            [],
            ['REMOTE_ADDR' => '203.0.113.10']
        );

        $limits = AppServiceProvider::buildLoginRateLimits($request);

        self::assertCount(2, $limits);

        self::assertSame(10, $limits[0]->maxAttempts);
        self::assertSame(60, $limits[0]->decaySeconds);
        self::assertSame('203.0.113.10|adminuser', $limits[0]->key);

        self::assertSame(30, $limits[1]->maxAttempts);
        self::assertSame(60, $limits[1]->decaySeconds);
        self::assertSame('203.0.113.10', $limits[1]->key);
    }

    public function test_global_rate_limiter_uses_authenticated_user_key(): void
    {
        $request = Request::create('/api/ib_applications/pending_count', 'GET');
        $request->setUserResolver(static fn () => new class {
            public function getAuthIdentifier(): int
            {
                return 42;
            }
        });

        $limit = AppServiceProvider::buildGlobalRateLimit($request);

        self::assertSame(120, $limit->maxAttempts);
        self::assertSame(60, $limit->decaySeconds);
        self::assertSame('user:42', $limit->key);
    }

    public function test_global_rate_limiter_falls_back_to_ip_key(): void
    {
        $request = Request::create(
            '/api/auth/captcha',
            'GET',
            [],
            [],
            [],
            ['REMOTE_ADDR' => '203.0.113.20']
        );

        $limit = AppServiceProvider::buildGlobalRateLimit($request);

        self::assertSame(60, $limit->maxAttempts);
        self::assertSame(60, $limit->decaySeconds);
        self::assertSame('ip:203.0.113.20', $limit->key);
    }
}
