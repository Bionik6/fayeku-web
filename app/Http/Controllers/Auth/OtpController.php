<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\Compta\PartnerInvitation;
use App\Services\Shared\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OtpController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $phone = session('otp_phone') ?? auth()->user()?->phone;

        if (! $phone) {
            return redirect()->route('login');
        }

        return view('pages.auth.verify-otp', [
            'maskedPhone' => $this->maskPhone($phone),
            'otpExpiresAt' => $this->latestOtpExpiresAt($phone),
        ]);
    }

    public function verify(VerifyOtpRequest $request, OtpService $otpService): JsonResponse|RedirectResponse
    {
        $phone = session('otp_phone') ?? auth()->user()?->phone;

        if (! $phone) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expirée.'], 422);
            }

            return redirect()->route('login');
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

        $invitationToken = session('invitation_token');

        if ($invitationToken) {
            $invitation = PartnerInvitation::where('token', $invitationToken)
                ->where('status', 'registering')
                ->first();

            if ($invitation) {
                $invitation->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                ]);
            }

            session()->forget('invitation_token');
        }

        session()->forget('otp_phone');

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Téléphone vérifié avec succès.']);
        }

        if ($user?->profile_type === 'sme') {
            $company = $user->smeCompany();

            if ($company && ! $company->isSetupComplete()) {
                return redirect()->route('auth.company-setup');
            }
        }

        return redirect()->route($this->dashboardRouteNameForUser());
    }

    public function resend(OtpService $otpService): JsonResponse|RedirectResponse
    {
        $phone = session('otp_phone') ?? auth()->user()?->phone;

        if (! $phone) {
            if (request()->expectsJson()) {
                return response()->json(['message' => 'Session expirée.'], 422);
            }

            return redirect()->route('login');
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

    private function dashboardRouteNameForUser(): string
    {
        $user = auth()->user();

        return $user?->profile_type === 'sme' ? 'pme.dashboard' : 'dashboard';
    }

    private function latestOtpExpiresAt(string $phone, string $purpose = 'verification'): ?int
    {
        $record = DB::table('otp_codes')
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        return $record ? strtotime($record->expires_at) : null;
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
