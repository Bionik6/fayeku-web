<?php

namespace Modules\Shared\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && is_null(auth()->user()->phone_verified_at)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Phone not verified.'], 403);
            }
            return redirect()->route('auth.otp');
        }
        return $next($request);
    }
}
