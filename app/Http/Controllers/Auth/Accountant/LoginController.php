<?php

namespace App\Http\Controllers\Auth\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Accountant\LoginRequest;
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
        return view('pages.auth.accountant.login');
    }

    public function store(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $email = Str::lower($request->input('email'));
        $throttleKey = $email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $message = "Trop de tentatives. Réessayez dans {$seconds} secondes.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 429);
            }

            return back()->withErrors(['email' => $message])->withInput();
        }

        $credentials = [
            'email' => $email,
            'password' => $request->input('password'),
            'profile_type' => 'accountant_firm',
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            $message = 'Identifiants incorrects.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 401);
            }

            return back()->withErrors(['email' => $message])->withInput();
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

            return back()->withErrors(['email' => $message])->withInput();
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
}
