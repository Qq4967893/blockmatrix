<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\Permission;
use App\Services\Blockmatrix\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class PermissionMiddlewareTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public static function filterListApiProvider(): array
    {
        return [
            '自身权限数据' => ['api/managers/permissions'],
            '语言选项字典' => ['api/get_lang'],
        ];
    }

    /**
     * @dataProvider filterListApiProvider
     */
    public function test_filter_list_api_bypasses_menu_permission_check(string $apiPath): void
    {
        $user = (object) ['id' => 1, 'login_name' => 'edgar'];
        Auth::shouldReceive('user')->andReturn($user);

        // 用户菜单里没有该接口，但接口在免权限名单中（登录后获取基础数据/自身数据的接口）
        $authService = Mockery::mock(AuthService::class);
        $authService->shouldReceive('modMethod')->andReturnUsing(fn (string $path): string => $path);
        $authService->shouldReceive('getUserMenu')->once()->andReturn(['api/roles', 'api/menus']);
        $middleware = new Permission($authService);

        $passed = false;
        $response = $middleware->handle(
            Request::create('/' . $apiPath, 'GET'),
            function () use (&$passed) {
                $passed = true;

                return response()->json(['ok' => true]);
            }
        );

        $this->assertTrue($passed);
        $this->assertTrue($response->getData(true)['ok']);
    }

    public function test_denies_path_not_in_user_menus(): void
    {
        $user = (object) ['id' => 1, 'login_name' => 'edgar'];
        Auth::shouldReceive('user')->andReturn($user);

        $authService = Mockery::mock(AuthService::class);
        $authService->shouldReceive('modMethod')->andReturnUsing(fn (string $path): string => $path);
        $authService->shouldReceive('getUserMenu')->once()->andReturn(['api/managers/permissions']);
        $middleware = new Permission($authService);

        $response = $middleware->handle(
            Request::create('/api/roles', 'GET'),
            fn () => response()->json(['ok' => true])
        );

        $this->assertFalse($response->getData(true)['ok'] ?? false);
        $this->assertSame(trans('middleware.permission.access_denied'), $response->getData(true)['message'] ?? '');
    }

    public function test_requires_login(): void
    {
        Auth::shouldReceive('user')->andReturn(null);

        $authService = Mockery::mock(AuthService::class);
        $authService->shouldReceive('modMethod')->andReturnUsing(fn (string $path): string => $path);
        $middleware = new Permission($authService);

        $response = $middleware->handle(
            Request::create('/api/managers/permissions', 'GET'),
            fn () => response()->json(['ok' => true])
        );

        $this->assertSame(trans('middleware.permission.login_required'), $response->getData(true)['message'] ?? '');
    }
}
