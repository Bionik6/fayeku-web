<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Auth\Http\Requests\VerifyOtpRequest;
use Modules\Shared\Services\OtpService;

class OtpController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $phone = session('otp_phone') ?? auth()->user()?->phone;

        if (! $phone) {
            return redirect()->route('auth.login');
        }

        return view('pages.auth.verify-otp', [
            'maskedPhone' => $this->maskPhone($phone),
        ]);
    }

    public function verify(VerifyOtpRequest $request, OtpService $otpService): JsonResponse|RedirectResponse
    {
        $phone = session('otp_phone') ?? auth()->user()?->phone;

        if (! $phone) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expirée.'], 422);
            }

            return redirect()->route('auth.login');
        }

        if (! $otpService->verify($phone, $request->input('code'))) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Code invalide ou expiré.'], 422);
            }

            return back()->withErrors(['code' => 'Code invalide ou expiré.']);
        }

        $user = auth()->user();

        if ($user) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        session()->forget('otp_phone');

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Téléphone vérifié avec succès.']);
        }

        return redirect()->route('dashboard');
    }

    public function resend(OtpService $otpService): JsonResponse|RedirectResponse
    {
        $phone = session('otp_phone') ?? auth()->user()?->phone;

        if (! $phone) {
            if (request()->expectsJson()) {
                return response()->json(['message' => 'Session expirée.'], 422);
            }

            return redirect()->route('auth.login');
        }

        if (! $otpService->canResend($phone)) {
            $message = 'Veuillez patienter avant de renvoyer un code.';

            if (request()->expectsJson()) {
                return response()->json(['message' => $message], 429);
            }

            return back()->with('status', $message);
        }

        $otpService->generate($phone);

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Un nouveau code a été envoyé.']);
        }

        return back()->with('status', 'Un nouveau code a été envoyé.');
    }

    private function maskPhone(string $phone): string
    {
        $length = mb_strlen($phone);

        if ($length <= 6) {
            return $phone;
        }

        return mb_substr($phone, 0, 4).str_repeat('*', $length - 6).mb_substr($phone, -2);
    }
}
