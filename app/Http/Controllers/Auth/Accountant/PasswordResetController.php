<?php

namespace App\Http\Controllers\Auth\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\Accountant\ResetPasswordRequest;
use App\Models\Shared\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function showResetForm(string $token, Request $request): View
    {
        return view('pages.auth.accountant.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(ResetPasswordRequest $request): RedirectResponse
    {
        $email = Str::lower($request->input('email'));

        $status = Password::broker()->reset(
            [
                'email' => $email,
                'profile_type' => 'accountant_firm',
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        $user = User::where('email', $email)
            ->where('profile_type', 'accountant_firm')
            ->first();

        if ($user) {
            Auth::login($user);

            return redirect()->intended(route('dashboard'));
        }

        return redirect()->route('login')
            ->with('status', 'Mot de passe réinitialisé. Connectez-vous.');
    }
}
