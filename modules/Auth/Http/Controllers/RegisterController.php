<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Auth\Services\AuthService;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('pages.auth.register');
    }

    public function store(RegisterRequest $request, AuthService $authService): JsonResponse|RedirectResponse
    {
        $user = $authService->register($request->validated());

        Auth::login($user);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Inscription réussie. Veuillez vérifier votre téléphone.',
                'user' => $user,
                'token' => $user->createToken('auth')->plainTextToken,
            ], 201);
        }

        session(['otp_phone' => $user->phone]);

        return redirect()->route('auth.otp');
    }
}
