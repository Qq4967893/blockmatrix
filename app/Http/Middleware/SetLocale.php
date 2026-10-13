<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class SetLocale
{
    /**
     * 根据客户端语言偏好设置应用语言环境。
     */
    public function handle(Request $request, Closure $next)
    {
        $supportedLocales = array_keys(config('lang.languages', []));
        $defaultLocale    = (string) config('app.locale', 'en_US');
        $locale           = (string) $request->cookie('lang', $defaultLocale);

        if (! empty($supportedLocales) && ! in_array($locale, $supportedLocales, true)) {
            $locale = $defaultLocale;
        }

        app()->setLocale($locale);
        Config::set('app.locale', $locale);

        return $next($request);
    }
}
