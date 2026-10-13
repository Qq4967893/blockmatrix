<?php

namespace App\Http\Controllers\Blockmatrix;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Blockmatrix\LoginService;
use App\Utils\BaseRedis;
use App\Utils\CaptchaUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Scaffold\Framework\Utils\Ip;

class LoginController extends BaseController
{

    public function __construct(
        private LoginService $loginService
    ) {
    }

    /**
     * 处理用户登录请求
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $loginName = $request->input('login_name');
        $password = $request->input('password');
        $key = $request->input('key');
        $captcha = $request->input('captcha');

        //验证码校验
        if (empty(config('blockmatrix.captcha.none'))) {
            $captchaKey = config('blockmatrix.redis.captcha') . $key;
            $redis = BaseRedis::getBlockmatrixRedis();
            $checkCode = $redis->get($captchaKey);

            if (empty($checkCode)) {
                return $this->fail(trans('login.captcha_expired'));
            }
            if ($checkCode !== strtolower($captcha)) {
                return $this->fail(trans('login.captcha_incorrect'));
            }

            $redis->del($captchaKey);
        }

        //用户名密码验证（模糊错误，避免枚举）
        $userInfo = $this->loginService->getUserInfoByAccount($loginName);
        if (empty($userInfo) || !Hash::check($password, $userInfo['password'])) {
            return $this->fail(trans('login.account_no'));
        }

        //登录应用
        Auth::loginUsingId($userInfo['id']);
        // 防止会话固定，登录后刷新 session ID
        $request->session()->regenerate();

        //存储该用户大区
        $userId = Auth::id();
        $areaId = $this->loginService->userRoleArea($userId);
        $request->session()->put('area_id', $areaId);

        //客户端ip
        $ip = Ip::getClientIP();

        Log::info("{$loginName}[{$ip}]登录成功");

        if (!Auth::check()) {
            return $this->fail(trans('login.login_error'));
        }

        return $this->succeed([], trans('login.login_success'));
    }

    /**
     * 生成验证码
     *
     * @return JsonResponse
     */
    public function captcha(): JsonResponse
    {
        $key = Str::lower((string) Str::uuid());
        $captchaKey = config('blockmatrix.redis.captcha') . $key;
        $captcha = CaptchaUtil::captcha($captchaKey);
        $captcha['key'] = $key;

        return $this->succeed($captcha);
    }

    /**
     * 退出登录
     *
     * @param Request $request
     *
     * @return JsonResponse
     */

    public function logout(Request $request): JsonResponse
    {
        $user = Auth::user();

        //退出登录日志
        if ($user) {
            Log::info($user['username'] . '退出登录');
        }
        Auth::logout();
        //刷新会话数据并重新生成ID(防止账号间切换会话不刷新问题)
        $request->session()->invalidate();

        return $this->succeed();
    }

    /**
     * 获取 CSRF Token
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function csrfToken(Request $request): JsonResponse
    {
        return $this->succeed([
            'csrf_token' => csrf_token(),
        ], 'CSRF token retrieved successfully');
    }
}
