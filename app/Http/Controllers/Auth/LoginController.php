<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Shared\User;
use App\Services\Auth\AuthService;
use App\Services\Shared\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('pages.auth.login');
    }

    public function store(LoginRequest $request, OtpService $otpService): JsonResponse|RedirectResponse
    {
        $profile = $request->input('profile');

        if ($profile === 'accountant') {
            return $this->authenticateAccountant($request);
        }

        return $this->authenticateSme($request, $otpService);
    }

    private function authenticateSme(LoginRequest $request, OtpService $otpService): JsonResponse|RedirectResponse
    {
        $normalizedPhone = AuthService::normalizePhone(
            $request->input('phone'),
            $request->input('country_code')
        );

        $throttleKey = 'sme:'.$normalizedPhone.'|'.$request->ip();

        if ($response = $this->throttleResponse($request, $throttleKey, 'phone')) {
            return $response;
        }

        $credentials = [
            'phone' => $normalizedPhone,
            'password' => $request->input('password'),
            'profile_type' => 'sme',
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            return $this->failedAttempt($request, 'phone');
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return $this->disabledAccount($request, 'phone');
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

            return redirect()->route('sme.auth.otp');
        }

        return redirect()->intended(route('pme.dashboard'));
    }

    private function authenticateAccountant(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $email = Str::lower($request->input('email'));
        $throttleKey = 'accountant:'.$email.'|'.$request->ip();

        if ($response = $this->throttleResponse($request, $throttleKey, 'email')) {
            return $response;
        }

        $credentials = [
            'email' => $email,
            'password' => $request->input('password'),
            'profile_type' => 'accountant_firm',
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            return $this->failedAttempt($request, 'email');
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return $this->disabledAccount($request, 'email');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Connexion réussie.',
                'user' => $user,
                'token' => $user->createToken('auth')->plainTextToken,
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function throttleResponse(LoginRequest $request, string $throttleKey, string $errorField): JsonResponse|RedirectResponse|null
    {
        if (! RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return null;
        }

        $seconds = RateLimiter::availableIn($throttleKey);
        $message = "Trop de tentatives. Réessayez dans {$seconds} secondes.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 429);
        }

        return back()->withErrors([$errorField => $message])->withInput();
    }

    private function failedAttempt(LoginRequest $request, string $errorField): JsonResponse|RedirectResponse
    {
        $message = 'Identifiants incorrects.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return back()->withErrors([$errorField => $message])->withInput();
    }

    private function disabledAccount(LoginRequest $request, string $errorField): JsonResponse|RedirectResponse
    {
        $message = 'Votre compte est désactivé.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return back()->withErrors([$errorField => $message])->withInput();
    }
}
