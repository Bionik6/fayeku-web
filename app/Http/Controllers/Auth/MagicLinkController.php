<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MagicLinkRequest;
use App\Mail\Auth\MagicLinkMail;
use App\Models\Shared\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MagicLinkController extends Controller
{
    private const EXPIRES_MINUTES = 15;

    public function request(): View
    {
        return view('pages.auth.magic-link');
    }

    public function send(MagicLinkRequest $request): JsonResponse|RedirectResponse
    {
        $email = Str::lower((string) $request->input('email'));

        $user = User::where('email', $email)
            ->where('is_active', true)
            ->first();

        if ($user) {
            $magicUrl = URL::temporarySignedRoute(
                'auth.magic-link.consume',
                now()->addMinutes(self::EXPIRES_MINUTES),
                ['user' => $user->id],
            );

            Mail::to($user->email)->send(new MagicLinkMail(
                firstName: (string) $user->first_name,
                magicUrl: $magicUrl,
                expiresInMinutes: self::EXPIRES_MINUTES,
            ));
        }

        $message = 'Si cette adresse est associée à un compte, un lien de connexion vous a été envoyé.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message)->withInput();
    }

    public function consume(Request $request, string $user): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login')
                ->withErrors(['identifier' => 'Ce lien est invalide ou expiré.']);
        }

        $authUser = User::where('id', $user)
            ->where('is_active', true)
            ->first();

        if (! $authUser) {
            return redirect()->route('login')
                ->withErrors(['identifier' => 'Ce lien est invalide ou expiré.']);
        }

        if (is_null($authUser->email_verified_at)) {
            $authUser->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($authUser);
        $request->session()->regenerate();

        return redirect()->intended($authUser->dashboardUrl());
    }
}
