<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Shared\User;
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

    public function store(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $email = Str::lower(trim((string) $request->input('email')));
        $throttleKey = 'email:'.$email.'|'.$request->ip();

        if ($response = $this->throttleResponse($request, $throttleKey)) {
            return $response;
        }

        $credentials = [
            'email' => $email,
            'password' => $request->input('password'),
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            return $this->failedAttempt($request);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            RateLimiter::hit($throttleKey, 60);

            return $this->disabledAccount($request);
        }

        RateLimiter::clear($throttleKey);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Connexion réussie.',
                'user' => $user,
                'token' => $user->createToken('auth')->plainTextToken,
                'email_verified' => ! is_null($user->email_verified_at),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended($user->dashboardUrl());
    }

    private function throttleResponse(LoginRequest $request, string $throttleKey): JsonResponse|RedirectResponse|null
    {
        if (! RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return null;
        }

        $seconds = RateLimiter::availableIn($throttleKey);
        $message = "Trop de tentatives. Réessayez dans {$seconds} secondes.";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 429);
        }

        return back()->withErrors(['email' => $message])->withInput();
    }

    private function failedAttempt(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $message = 'Identifiants incorrects.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return back()->withErrors(['email' => $message])->withInput();
    }

    private function disabledAccount(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $message = 'Votre compte est désactivé.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return back()->withErrors(['email' => $message])->withInput();
    }
}
