<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Compta\AccountantLead;
use App\Models\Shared\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AccountantActivationController extends Controller
{
    /**
     * Message flash affiché sur /login quand le lien d'activation a déjà été
     * utilisé (token invalidé), a expiré, ou ne correspond à rien. UX commune
     * pour ces 3 cas — pas de 404 brutal, on guide vers la connexion.
     */
    private const INVALID_LINK_FLASH = "Ce lien d'activation n'est plus valide. Si votre compte est déjà activé, connectez-vous avec votre adresse email.";

    public function show(string $token): View|RedirectResponse
    {
        $lead = $this->findValidLead($token);

        if (! $lead) {
            return $this->redirectToLoginWithExpiredFlash();
        }

        return view('pages.auth.accountant-activation', [
            'lead' => $lead,
            'token' => $token,
        ]);
    }

    public function process(Request $request, string $token): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'cgu_accepted' => ['accepted'],
        ], [
            'password.min' => 'Le mot de passe doit faire au moins 8 caractères.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
            'cgu_accepted.accepted' => 'Vous devez accepter les CGU pour continuer.',
        ]);

        $lead = $this->findValidLead($token);

        if (! $lead) {
            return $this->redirectToLoginWithExpiredFlash();
        }

        DB::transaction(function () use ($lead, $request) {
            $user = User::findOrFail($lead->user_id);

            $user->forceFill([
                'password' => Hash::make($request->string('password')->toString()),
                'is_active' => true,
                'email_verified_at' => now(),
                'phone_verified_at' => $user->phone_verified_at ?? now(),
            ])->save();

            $lead->invalidateActivationToken();

            Auth::login($user);
        });

        return redirect()
            ->route('dashboard')
            ->with('welcome_new_user', true);
    }

    private function findValidLead(string $token): ?AccountantLead
    {
        $lead = AccountantLead::where('activation_token_hash', hash('sha256', $token))
            ->whereNotNull('user_id')
            ->where('activation_token_expires_at', '>', now())
            ->first();

        return ($lead && $lead->isActivationTokenValid($token)) ? $lead : null;
    }

    private function redirectToLoginWithExpiredFlash(): RedirectResponse
    {
        return redirect()->route('login')->with('status', self::INVALID_LINK_FLASH);
    }
}
