<?php

namespace Modules\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        if (auth()->check() && auth()->user()->profile_type !== $type) {
            abort(403, 'Access denied for this profile type.');
        }

        return $next($request);
    }
}
