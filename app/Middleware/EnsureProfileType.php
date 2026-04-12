<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        if (! auth()->check()) {
            return redirect('/');
        }

        if (auth()->user()->profile_type !== $type) {
            return redirect($this->dashboardUrl(auth()->user()->profile_type));
        }

        return $next($request);
    }

    private function dashboardUrl(string $profileType): string
    {
        return match ($profileType) {
            'sme' => route('pme.dashboard'),
            'accountant_firm' => route('dashboard'),
            default => '/',
        };
    }
}
