<?php

namespace App\Http\Controllers\Compta;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Models\Auth\Company;

class JoinController extends Controller
{
    public function __invoke(string $code): RedirectResponse
    {
        $firm = Company::where('invite_code', strtoupper($code))
            ->where('type', 'accountant_firm')
            ->firstOrFail();

        if (auth()->check()) {
            $route = auth()->user()->profile_type === 'sme' ? 'pme.dashboard' : 'dashboard';

            return redirect()->route($route);
        }

        session(['joining_firm_code' => $firm->invite_code]);

        return redirect()->route('auth.register');
    }
}
