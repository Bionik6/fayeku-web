<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Shared\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            $request->user()?->currentAccessToken()?->delete();

            return response()->json(['message' => 'Déconnexion réussie.']);
        }

        /** @var User|null $user */
        $user = Auth::guard('web')->user();
        $profileType = $user?->profile_type;

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirectRoute = match ($profileType) {
            'accountant_firm' => 'accountant.auth.login',
            'sme' => 'sme.auth.login',
            default => 'home',
        };

        return redirect()->route($redirectRoute);
    }
}
