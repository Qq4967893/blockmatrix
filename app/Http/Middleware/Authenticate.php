<?php

namespace App\Http\Middleware;

use App\Repositories\Blockmatrix\AdminUserRepository;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Closure;
use Scaffold\Framework\Traits\Response;

class Authenticate
{
    use Response;

    /**
     * Create a new filter instance.
     *
     * @param Guard               $auth           认证信息
     * @param AdminUserRepository $userRepository 用户
     */
    public function __construct(
        private Guard               $auth,
        private AdminUserRepository $userRepository
    ) {
    }


    /**
     * Handle an incoming request.
     *
     * @param object  $request 请求信息
     * @param Closure $next    匿名函数
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            return $this->fail(trans('middleware.permission.login_required'), 10000);
        }

        //设置登录用户信息
        $loginUser = Auth::user()['login_name'] . '(' . Auth::user()['id'] . ')';
        define('LOGIN_USER', $loginUser);

        //设置角色数据权限

        return $next($request);
    }
}
