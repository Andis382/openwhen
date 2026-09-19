<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Each person reads the app in their own language. */
class SetUserLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->user()?->locale ?: config('openwhen.defaults.locale');

        if (array_key_exists($locale, config('openwhen.locales'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
