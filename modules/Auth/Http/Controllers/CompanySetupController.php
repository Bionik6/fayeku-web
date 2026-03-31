<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Auth\Http\Requests\CompanySetupRequest;

class CompanySetupController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $company = $request->user()->smeCompany();

        if (! $company || $company->isSetupComplete()) {
            return redirect()->route('pme.dashboard');
        }

        return view('pages.auth.company-setup', [
            'prefillCompanyName' => session('invitee_company_name'),
        ]);
    }

    public function store(CompanySetupRequest $request): RedirectResponse
    {
        $company = $request->user()->smeCompany();

        if (! $company || $company->isSetupComplete()) {
            return redirect()->route('pme.dashboard');
        }

        $company->update([
            'name' => $request->validated('company_name'),
            'sector' => $request->validated('sector'),
            'ninea' => $request->validated('ninea'),
            'rccm' => $request->validated('rccm'),
            'setup_completed_at' => now(),
        ]);

        session()->forget('invitee_company_name');

        return redirect()->route('pme.dashboard')->with('welcome_new_user', true);
    }
}
