<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Auth\Company;
use App\Services\Auth\AuthService;
use App\Models\Compta\PartnerInvitation;

class RegisterController extends Controller
{
    public function show(Request $request): View
    {
        $invitation = null;
        $joiningFirm = null;
        $inviteePhone = null;

        // Old flow: specific invitation token via ?join= or session
        $token = $request->query('join') ?? session('invitation_token');

        if ($token) {
            $invitation = PartnerInvitation::with('accountantFirm')
                ->where('token', $token)
                ->where('status', 'pending')
                ->first();

            if ($invitation?->invitee_phone) {
                $inviteePhone = AuthService::parseInternationalPhone($invitation->invitee_phone);
            }
        }

        // New flow: firm-level join code stored in session by JoinController
        if (! $invitation && session('joining_firm_code')) {
            $joiningFirm = Company::where('invite_code', session('joining_firm_code'))
                ->where('type', 'accountant_firm')
                ->first();
        }

        return view('pages.auth.register', [
            'invitation' => $invitation,
            'joiningFirm' => $joiningFirm,
            'inviteePhone' => $inviteePhone,
        ]);
    }

    public function store(RegisterRequest $request, AuthService $authService): JsonResponse|RedirectResponse
    {
        $invitation = null;
        $invitingFirm = null;

        // Old flow: specific invitation token submitted in form
        $token = $request->validated('invitation_token');

        if ($token) {
            $invitation = PartnerInvitation::with('accountantFirm')
                ->where('token', $token)
                ->where('status', 'pending')
                ->first();
        }

        // New flow: firm-level join via session
        if (! $invitation && session('joining_firm_code')) {
            $firm = Company::where('invite_code', session('joining_firm_code'))
                ->where('type', 'accountant_firm')
                ->first();

            if ($firm) {
                // Try to match a pending invitation for this phone + firm
                $normalizedPhone = AuthService::normalizePhone(
                    $request->input('phone'),
                    $request->input('country_code')
                );

                $invitation = PartnerInvitation::with('accountantFirm')
                    ->where('accountant_firm_id', $firm->id)
                    ->where('invitee_phone', $normalizedPhone)
                    ->where('status', 'pending')
                    ->first();

                // If no specific invitation, still link to the firm
                if (! $invitation) {
                    $invitingFirm = $firm;
                }
            }
        }

        $user = $authService->register($request->validated(), $invitation, $invitingFirm);

        Auth::login($user);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Inscription réussie. Veuillez vérifier votre téléphone.',
                'user' => $user,
                'token' => $user->createToken('auth')->plainTextToken,
            ], 201);
        }

        session(['otp_phone' => $user->phone]);

        if ($invitation) {
            session(['invitation_token' => $invitation->token]);

            if ($invitation->invitee_company_name) {
                session(['invitee_company_name' => $invitation->invitee_company_name]);
            }
        }

        session()->forget('joining_firm_code');

        return redirect()->route('auth.otp');
    }
}
