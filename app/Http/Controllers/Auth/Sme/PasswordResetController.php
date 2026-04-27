<?php

namespace App\Http\Controllers\Auth\Sme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Sme\ForgotPasswordRequest;
use App\Http\Requests\Auth\Sme\ResetPasswordRequest;
use App\Models\Shared\User;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showForgotForm(): View
    {
        return view('pages.auth.sme.forgot-password');
    }

    public function sendResetOtp(ForgotPasswordRequest $request, AuthService $authService): JsonResponse|RedirectResponse
    {
        $normalizedPhone = AuthService::normalizePhone(
            $request->input('phone'),
            $request->input('country_code')
        );

        $authService->requestPasswordReset($normalizedPhone);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Si ce numéro est enregistré, un code vous a été envoyé.',
            ]);
        }

        session([
            'reset_phone' => $normalizedPhone,
            'reset_country_code' => $request->input('country_code'),
        ]);

        return redirect()->route('sme.auth.reset-password')
            ->with('status', 'Si ce numéro est enregistré, un code vous a été envoyé.');
    }

    public function showResetForm(): View|RedirectResponse
    {
        if (! session('reset_phone')) {
            return redirect()->route('sme.auth.forgot-password');
        }

        return view('pages.auth.sme.reset-password');
    }

    public function reset(ResetPasswordRequest $request, AuthService $authService): JsonResponse|RedirectResponse
    {
        $phone = session('reset_phone') ?? $request->input('phone');

        if (! $phone) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expirée.'], 422);
            }

            return redirect()->route('sme.auth.forgot-password');
        }

        if (! $authService->resetPassword($phone, $request->input('code'), $request->input('password'))) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Code invalide ou expiré.'], 422);
            }

            return back()->withErrors(['code' => 'Code invalide ou expiré.']);
        }

        session()->forget(['reset_phone', 'reset_country_code']);

        $user = User::where('phone', $phone)
            ->where('profile_type', 'sme')
            ->first();

        if ($user) {
            Auth::login($user);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Mot de passe réinitialisé avec succès.',
                'token' => $user?->createToken('auth')->plainTextToken,
            ]);
        }

        return redirect()->route('pme.dashboard');
    }
}
