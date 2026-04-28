<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('pages.auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request, AuthService $authService): JsonResponse|RedirectResponse
    {
        if ($request->input('profile') === 'accountant') {
            return $this->sendResetLink($request);
        }

        return $this->sendResetOtp($request, $authService);
    }

    private function sendResetOtp(ForgotPasswordRequest $request, AuthService $authService): JsonResponse|RedirectResponse
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

    private function sendResetLink(ForgotPasswordRequest $request): JsonResponse|RedirectResponse
    {
        $email = Str::lower($request->input('email'));

        Password::broker()->sendResetLink([
            'email' => $email,
            'profile_type' => 'accountant_firm',
        ]);

        $message = 'Si cette adresse est associée à un compte, un lien de réinitialisation vous a été envoyé.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message)->withInput();
    }
}
