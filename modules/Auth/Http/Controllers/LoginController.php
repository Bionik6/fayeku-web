<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Services\AuthService;
use Modules\Shared\Models\User;
use Modules\Shared\Services\OtpService;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('pages.auth.login');
    }

    public function store(LoginRequest $request, OtpService $otpService): JsonResponse|RedirectResponse
    {
        $throttleKey = $request->input('phone').'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $message = "Trop de tentatives. Réessayez dans {$seconds} secondes.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 429);
            }

            return back()->withErrors(['phone' => $message])->withInput();
        }

        $normalizedPhone = AuthService::normalizePhone(
            $request->input('phone'),
            $request->input('country_code')
        );

        if (! Auth::attempt(['phone' => $normalizedPhone, 'password' => $request->input('password')], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            $message = 'Identifiants incorrects.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 401);
            }

            return back()->withErrors(['phone' => $message])->withInput();
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            $message = 'Votre compte est désactivé.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            return back()->withErrors(['phone' => $message])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Connexion réussie.',
                'user' => $user,
                'token' => $user->createToken('auth')->plainTextToken,
                'phone_verified' => ! is_null($user->phone_verified_at),
            ]);
        }

        $request->session()->regenerate();

        if (is_null($user->phone_verified_at)) {
            $otpService->generate($user->phone);
            session(['otp_phone' => $user->phone]);

            return redirect()->route('auth.otp');
        }

        return redirect()->intended(route('dashboard'));
    }
}
