<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Auth\Services\AuthService;
use Modules\Compta\Partnership\Models\PartnerInvitation;

class RegisterController extends Controller
{
    public function show(Request $request): View
    {
        $invitation = null;
        $token = $request->query('join') ?? session('invitation_token');

        if ($token) {
            $invitation = PartnerInvitation::with('accountantFirm')
                ->where('token', $token)
                ->where('status', 'pending')
                ->first();
        }

        $inviteePhone = null;

        if ($invitation?->invitee_phone) {
            $inviteePhone = AuthService::parseInternationalPhone($invitation->invitee_phone);
        }

        return view('pages.auth.register', [
            'invitation' => $invitation,
            'inviteePhone' => $inviteePhone,
        ]);
    }

    public function store(RegisterRequest $request, AuthService $authService): JsonResponse|RedirectResponse
    {
        $invitation = null;
        $token = $request->validated('invitation_token');

        if ($token) {
            $invitation = PartnerInvitation::where('token', $token)
                ->where('status', 'pending')
                ->first();
        }

        $user = $authService->register($request->validated(), $invitation);

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
        }

        return redirect()->route('auth.otp');
    }
}
