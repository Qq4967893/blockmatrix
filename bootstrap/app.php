<?php

use App\Exceptions\BusinessException;
use App\Http\Middleware\Authenticate;
use App\Http\Middleware\Permission;
use App\Http\Middleware\RouteRequestLockMiddleware;
use App\Http\Middleware\ScrambleDocsBasicAuth;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Utils\EmailReportError;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', SetLocale::class);

        $middleware->alias([
            'auth'       => Authenticate::class,
            'docs.basic' => ScrambleDocsBasicAuth::class,
            'permission' => Permission::class,
            'route.lock' => RouteRequestLockMiddleware::class,
        ]);

        $middleware->encryptCookies(except: ['lang']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'password',
            'password_confirmation',
        ]);

        $exceptions->report(function (Throwable $e) {
            if ($e instanceof HttpException) {
                Log::error(sprintf(
                    '路由异常 [%s]:[%s,%s], errMsg:%s,%s',
                    request()->ip(),
                    request()->path(),
                    request()->method(),
                    $e->getStatusCode(),
                    $e->getMessage()
                ));
                return false;
            }

            Log::error('系统异常', ['exception' => $e]);
            EmailReportError::sendExceptionEmail('系统异常', $e);

            return false;
        });

        $exceptions->render(function (BusinessException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'is_succ' => false,
                    'code' => $e->businessCode,
                    'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Server Error',
                    'data' => null,
                ], $e->status);
            }
        });

        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*')) {
                if ($e instanceof ValidationException) {
                    $errors = $e->validator->errors()->all();

                    return response()->json([
                        'is_succ' => false,
                        'code'    => 422,
                        'message' => $errors[0],
                    ], 422);
                }

                $isHttpException = method_exists($e, 'getStatusCode');
                $statusCode      = $isHttpException ? $e->getStatusCode() : 500;
                $businessCode    = $isHttpException ? $statusCode : 0;
                $message = $isHttpException
                    ? ($e->getMessage() ?: 'Server Error')
                    : (app()->environment('local')
                        ? ($e->getMessage() ?: 'Server Error')
                        : 'Server Error');

                return response()->json([
                    'is_succ' => false,
                    'code'    => $businessCode,
                    'message' => $message,
                    'data'    => null,
                ], $statusCode);
            }
        });
    })
    ->create();
