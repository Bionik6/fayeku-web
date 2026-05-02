<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('fayeku.require_email_verification', true)) {
            return $next($request);
        }

        if (auth()->check() && is_null(auth()->user()->email_verified_at)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Email not verified.'], 403);
            }

            return redirect()->route('auth.verify-email');
        }

        return $next($request);
    }
}
