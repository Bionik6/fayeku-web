<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force les utilisateurs PME à terminer leur onboarding avant d'accéder
 * au reste de l'espace PME. Le wizard `pme.onboarding` lui-même est exclu
 * pour éviter une boucle de redirection ; `auth.logout` aussi pour permettre
 * de quitter sa session pendant l'onboarding.
 */
class EnsureOnboardingCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->profile_type !== 'sme') {
            return $next($request);
        }

        if ($request->routeIs('pme.onboarding', 'pme.onboarding.*', 'auth.logout')) {
            return $next($request);
        }

        $company = $user->smeCompany();

        if ($company && $company->setup_completed_at === null) {
            return redirect()->route('pme.onboarding');
        }

        return $next($request);
    }
}
