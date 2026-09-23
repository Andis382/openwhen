<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route guard: `->middleware('role:OWNER,DISPATCHER')`. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user()?->hasRole(...$roles), 403, __('errors.forbidden'));

        return $next($request);
    }
}
