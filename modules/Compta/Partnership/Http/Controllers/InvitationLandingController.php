<?php

namespace Modules\Compta\Partnership\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Auth\Services\AuthService;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\Shared\Models\User;

class InvitationLandingController extends Controller
{
    public function __invoke(string $token): RedirectResponse|View
    {
        $invitation = PartnerInvitation::with('accountantFirm')
            ->where('token', $token)
            ->firstOrFail();

        if ($invitation->status === 'accepted') {
            return redirect()->route('login')
                ->with('status', __('Vous avez déjà un compte Fayeku. Connectez-vous.'));
        }

        if ($invitation->expires_at?->isPast() && $invitation->status === 'pending') {
            return view('partnership::invitation-expired', [
                'invitation' => $invitation,
            ]);
        }

        if ($invitation->invitee_phone) {
            $parsed = AuthService::parseInternationalPhone($invitation->invitee_phone);

            if (User::where('phone', $parsed['normalized'])->exists()) {
                return redirect()->route('login')
                    ->with('status', __('Vous êtes déjà inscrit sur Fayeku. Veuillez vous connecter.'));
            }
        }

        if ($invitation->link_opened_at === null) {
            $invitation->update(['link_opened_at' => now()]);
        }

        session(['invitation_token' => $token]);

        return redirect()->route('auth.register', ['join' => $token]);
    }
}
