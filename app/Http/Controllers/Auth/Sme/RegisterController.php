<?php

namespace App\Http\Controllers\Auth\Sme;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Sme\RegisterRequest;
use App\Models\Auth\Company;
use App\Models\Compta\PartnerInvitation;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function show(Request $request): View
    {
        $invitation = null;
        $joiningFirm = null;
        $inviteePhone = null;

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

        if (! $invitation && session('joining_firm_code')) {
            $joiningFirm = Company::where('invite_code', session('joining_firm_code'))
                ->where('type', 'accountant_firm')
                ->first();
        }

        return view('pages.auth.sme.register', [
            'invitation' => $invitation,
            'joiningFirm' => $joiningFirm,
            'inviteePhone' => $inviteePhone,
        ]);
    }

    public function store(RegisterRequest $request, AuthService $authService): JsonResponse|RedirectResponse
    {
        $invitation = null;
        $invitingFirm = null;

        $token = $request->validated('invitation_token');

        if ($token) {
            $invitation = PartnerInvitation::with('accountantFirm')
                ->where('token', $token)
                ->where('status', 'pending')
                ->first();
        }

        if (! $invitation && session('joining_firm_code')) {
            $firm = Company::where('invite_code', session('joining_firm_code'))
                ->where('type', 'accountant_firm')
                ->first();

            if ($firm) {
                $normalizedPhone = AuthService::normalizePhone(
                    $request->input('phone'),
                    $request->input('country_code')
                );

                $invitation = PartnerInvitation::with('accountantFirm')
                    ->where('accountant_firm_id', $firm->id)
                    ->where('invitee_phone', $normalizedPhone)
                    ->where('status', 'pending')
                    ->first();

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

        return redirect()->route('sme.auth.otp');
    }
}
