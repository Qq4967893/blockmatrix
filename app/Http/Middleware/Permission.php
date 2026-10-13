<?php

namespace App\Http\Middleware;

use App\Services\Blockmatrix\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Scaffold\Framework\Traits\Response;
use Closure;

class Permission
{
    use Response;
    /**
     * Create a new filter instance.
     *
     * @param AuthService $authService 认证服务
     */
    public function __construct(
        private AuthService $authService
    ) {
    }

    /**
     * 权限验证
     *
     * @param Request $request
     * @param Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if (empty($user)) {
            return $this->fail(trans('middleware.permission.login_required'));
        }
        $path = $request->path();
        if (empty($path)) {
            return $this->fail(trans('middleware.permission.invalid_request_url'));
        }
        $path   = $this->authService->modMethod($path);

        $method = $request->method();
        if ($method == 'PUT' || $method == 'DELETE' || $method == 'POST') {
            $path = $path . '/' . $method;
        }

        $menus = $this->getLoginUserMenu($user);

        //未获取到任何菜单权限
        if (empty($menus)) {
            return $this->fail(trans('middleware.permission.permission_required'));
        }

        //接口未添加 或 功能未配置
        if (! in_array($path, $menus) && ! in_array($path, self::$filterApiArr)) {
            return $this->fail(trans('middleware.permission.access_denied'));
        }

        return $next($request);
    }

    /**
     * 获取用户权限菜单
     *
     * @param object $user 用户信息
     *
     * @return array|mixed
     */
    private function getLoginUserMenu($user)
    {
       return $this->authService->getUserMenu($user);
    }

    /**
     * 不需要权限的接口
     */
    private static $filterApiArr = array(
        // 获取当前登录者自己的权限数据，任何登录用户都需要调用（前端渲染菜单/按钮依赖它）
        'api/managers/permissions',
        // 语言选项字典（读取 config），任何登录用户都可能调用
        'api/get_lang',
        //菜单收藏，登录用户个人数据，无需菜单权限
        'api/favorites/menus',
        'api/favorites/menus/POST',
        'api/favorites/menus/DELETE',
    );
}
