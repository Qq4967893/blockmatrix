<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Scaffold\Framework\Traits\Response;

class RouteRequestLockMiddleware
{
    use Response;

    /**
     * 对同用户同路由请求加锁，避免重复提交。
     *
     * @param int $timeout 锁持有时长（秒）
     * @param int $waitTimeout 等待锁时长（秒），0 表示不等待
     */
    public function handle(Request $request, Closure $next, int $timeout = 10, int $waitTimeout = 0)
    {
        $lockKey = $this->generateLockKey($request);
        $lock    = Cache::lock($lockKey, $timeout);
        $locked  = false;

        try {
            if ($waitTimeout > 0) {
                $lock->block($waitTimeout);
                $locked = true;
            } else {
                $locked = $lock->get();
                if (! $locked) {
                    return $this->fail(trans('middleware.route_request_lock.busy'), 0, 429);
                }
            }

            return $next($request);
        } catch (LockTimeoutException) {
            return $this->fail(trans('middleware.route_request_lock.busy'), 0, 429);
        } finally {
            if ($locked) {
                $lock->release();
            }
        }
    }

    /**
     * 生成锁 key：method + route + user(ip)。
     */
    protected function generateLockKey(Request $request): string
    {
        $route     = $request->route();
        $routeName = ($route ? $route->getName() : null) ?? $request->path();
        $method    = $request->method();
        $userId    = Auth::check() ? (string) $request->user()->id : $request->ip();

        $rawKey = "request_lock:{$method}:{$routeName}:user:{$userId}";

        return 'lock:' . md5($rawKey);
    }
}
