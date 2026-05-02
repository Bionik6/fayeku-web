<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function show(): View
    {
        return view('pages.auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): JsonResponse|RedirectResponse
    {
        $email = Str::lower($request->input('email'));

        Password::broker()->sendResetLink(['email' => $email]);

        $message = 'Si cette adresse est associée à un compte, un lien de réinitialisation vous a été envoyé.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message)->withInput();
    }
}
