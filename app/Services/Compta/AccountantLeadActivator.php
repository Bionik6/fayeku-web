<?php

namespace App\Services\Compta;

use App\Enums\Auth\CompanyRole;
use App\Mail\Compta\AccountantActivationLinkMail;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\Compta\AccountantLead;
use App\Models\Shared\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AccountantLeadActivator
{
    /**
     * Crée le User + Company + pivot + Subscription pour un lead qualifié,
     * génère un magic link et envoie l'email d'activation.
     */
    public function activate(AccountantLead $lead): void
    {
        if ($lead->status === 'activated') {
            throw new DomainException("Le lead {$lead->id} est déjà activé.");
        }

        DB::transaction(function () use ($lead) {
            $user = User::create([
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'phone' => $lead->phone,
                'email' => $lead->email,
                'password' => Hash::make(Str::random(32)),
                'profile_type' => 'accountant_firm',
                'country_code' => $lead->country_code,
                'is_active' => false,
            ]);

            $company = Company::create([
                'name' => $lead->firm,
                'type' => 'accountant_firm',
                'plan' => 'basique',
                'country_code' => $lead->country_code,
                'phone' => $lead->phone,
                'email' => $lead->email,
            ]);

            $company->users()->attach($user->id, ['role' => CompanyRole::Owner->value]);

            Subscription::create([
                'company_id' => $company->id,
                'plan_slug' => 'basique',
                'price_paid' => 0,
                'billing_cycle' => 'trial',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(60),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays(60),
            ]);

            $lead->forceFill([
                'status' => 'activated',
                'activated_at' => now(),
                'user_id' => $user->id,
                'company_id' => $company->id,
            ])->save();
        });

        $token = $lead->generateActivationToken();
        Mail::to($lead->email)->send(new AccountantActivationLinkMail($lead->fresh(), $token));
    }

    /**
     * Régénère un token et renvoie le magic link à un cabinet déjà activé.
     */
    public function resendActivation(AccountantLead $lead): void
    {
        if ($lead->status !== 'activated' || ! $lead->user_id) {
            throw new DomainException("Le lead {$lead->id} n'est pas activé.");
        }

        $token = $lead->generateActivationToken();
        Mail::to($lead->email)->send(new AccountantActivationLinkMail($lead->fresh(), $token));
    }
}
