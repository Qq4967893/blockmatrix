<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * 构建登录限流规则：
     * - 同一 IP + 账号名：每分钟 10 次
     * - 同一 IP：每分钟 30 次
     *
     * @return array<int, Limit>
     */
    public static function buildLoginRateLimits(\Illuminate\Http\Request $request): array
    {
        $loginName = mb_strtolower((string) $request->input('login_name', ''));
        $ipAddress = (string) $request->ip();

        return [
            Limit::perMinute(10)->by($ipAddress . '|' . $loginName),
            Limit::perMinute(30)->by($ipAddress),
        ];
    }

    public static function buildGlobalRateLimit(\Illuminate\Http\Request $request): Limit
    {
        $userId = $request->user()?->getAuthIdentifier();
        $key = $userId !== null && $userId !== ''
            ? 'user:' . $userId
            : 'ip:' . $request->ip();

        return $userId !== null && $userId !== ''
            ? Limit::perMinute(120)->by($key)
            : Limit::perMinute(60)->by($key);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (class_exists(Scramble::class)) {
            Scramble::configure()->expose(ui: 'api/docs', document: 'api/docs.json');
        }

        RateLimiter::for('login', fn (\Illuminate\Http\Request $request) => self::buildLoginRateLimits($request));

        RateLimiter::for('global', fn (\Illuminate\Http\Request $request) => self::buildGlobalRateLimit($request));
    }
}
