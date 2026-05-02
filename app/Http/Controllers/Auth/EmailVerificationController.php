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

class EmailVerificationController extends Controller
{
    private const PURPOSE = 'email_verification';

    public function show(): View|RedirectResponse
    {
        $email = $this->resolveEmail();

        if (! $email) {
            return redirect()->route('login');
        }

        return view('pages.auth.verify-email', [
            'maskedEmail' => $this->maskEmail($email),
            'otpExpiresAt' => $this->latestOtpExpiresAt($email),
        ]);
    }

    public function verify(VerifyOtpRequest $request, OtpService $otpService): JsonResponse|RedirectResponse
    {
        $email = $this->resolveEmail();

        if (! $email) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expirée.'], 422);
            }

            return redirect()->route('login');
        }

        if (! $otpService->verify($email, $request->input('code'), self::PURPOSE)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Code invalide ou expiré.'], 422);
            }

            return back()->withErrors(['code' => 'Code invalide ou expiré.']);
        }

        $user = auth()->user();

        if ($user) {
            $user->forceFill(['email_verified_at' => now()])->save();
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

        session()->forget('verification_email');

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Email vérifié avec succès.']);
        }

        if ($user?->profile_type === 'sme') {
            $company = $user->smeCompany();

            if ($company && ! $company->isSetupComplete()) {
                return redirect()->route('auth.company-setup');
            }
        }

        return redirect()->to($user?->dashboardUrl() ?? route('login'));
    }

    public function resend(OtpService $otpService): JsonResponse|RedirectResponse
    {
        $email = $this->resolveEmail();

        if (! $email) {
            if (request()->expectsJson()) {
                return response()->json(['message' => 'Session expirée.'], 422);
            }

            return redirect()->route('login');
        }

        if (! $otpService->canResend($email, self::PURPOSE)) {
            $message = 'Veuillez patienter avant de renvoyer un code.';

            if (request()->expectsJson()) {
                return response()->json(['message' => $message], 429);
            }

            return back()->with('status', $message);
        }

        $otpService->generate($email, self::PURPOSE);

        if (request()->expectsJson()) {
            return response()->json(['message' => 'Un nouveau code a été envoyé.']);
        }

        return back()->with('status', 'Un nouveau code a été envoyé.');
    }

    private function resolveEmail(): ?string
    {
        $email = session('verification_email') ?? auth()->user()?->email;

        return $email ? mb_strtolower((string) $email) : null;
    }

    private function latestOtpExpiresAt(string $email): ?int
    {
        $record = DB::table('otp_codes')
            ->where('identifier', $email)
            ->where('purpose', self::PURPOSE)
            ->whereNull('used_at')
            ->latest('created_at')
            ->first();

        return $record ? strtotime($record->expires_at) : null;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if (! $domain) {
            return $email;
        }

        $localLength = mb_strlen($local);

        if ($localLength <= 2) {
            return $local.'***@'.$domain;
        }

        return mb_substr($local, 0, 2).str_repeat('*', max(2, $localLength - 2)).'@'.$domain;
    }
}
