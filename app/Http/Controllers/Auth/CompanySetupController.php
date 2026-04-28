<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CompanySetupRequest;
use App\Models\Compta\PartnerInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

        $companyName = $request->validated('company_name');

        $company->update([
            'name' => $companyName,
            'sector' => $request->validated('sector'),
            'ninea' => $request->validated('ninea'),
            'rccm' => $request->validated('rccm'),
            'setup_completed_at' => now(),
        ]);

        // Backfill the cabinet's invitations dashboard with the real company name
        // (synthetic referral invitations leave invitee_company_name null until the
        // PME completes setup).
        PartnerInvitation::where('sme_company_id', $company->id)
            ->whereNull('invitee_company_name')
            ->update(['invitee_company_name' => $companyName]);

        session()->forget('invitee_company_name');

        return redirect()->route('pme.dashboard')->with('welcome_new_user', true);
    }
}
