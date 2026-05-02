<?php

namespace App\Services\Auth;

use App\Enums\Auth\CompanyRole;
use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\Compta\Commission;
use App\Models\Compta\PartnerInvitation;
use App\Models\Shared\User;
use App\Services\Compta\AccountantLeadActivator;
use App\Services\Compta\CommissionService;
use App\Services\Shared\OtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthService
{
    public function __construct(private OtpService $otpService) {}

    public static function normalizePhone(string $phone, string $countryCode): string
    {
        $prefix = config("fayeku.countries.{$countryCode}.prefix", '');
        $prefixDigits = preg_replace('/\D+/', '', $prefix) ?? '';
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($prefixDigits !== '' && str_starts_with($digits, $prefixDigits)) {
            return $prefix.ltrim(substr($digits, strlen($prefixDigits)), '0');
        }

        if (str_starts_with(trim($phone), '+')) {
            return '+'.ltrim($digits, '0');
        }

        return $prefix.ltrim($digits, '0');
    }

    /**
     * Parse an international phone number into country code and local number.
     *
     * @return array{country_code: string, local_number: string, normalized: string}
     */
    public static function parseInternationalPhone(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        foreach (config('fayeku.countries', []) as $code => $country) {
            $prefixDigits = preg_replace('/\D+/', '', $country['prefix']) ?? '';

            if ($prefixDigits !== '' && str_starts_with($digits, $prefixDigits)) {
                $localNumber = substr($digits, strlen($prefixDigits));

                return [
                    'country_code' => $code,
                    'local_number' => $localNumber,
                    'normalized' => $country['prefix'].ltrim($localNumber, '0'),
                ];
            }
        }

        return [
            'country_code' => 'SN',
            'local_number' => ltrim($digits, '0'),
            'normalized' => '+221'.ltrim($digits, '0'),
        ];
    }

    /**
     * Register a new SME user + company.
     *
     * Accountants don't go through this path — they are activated by an admin
     * via {@see AccountantLeadActivator}.
     */
    public function register(array $data, ?PartnerInvitation &$invitation = null, ?Company $invitingFirm = null): User
    {
        return DB::transaction(function () use ($data, &$invitation, $invitingFirm) {
            $phone = self::normalizePhone($data['phone'], $data['country_code']);
            $email = Str::lower(trim((string) $data['email']));

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $phone,
                'email' => $email,
                'password' => $data['password'],
                'profile_type' => 'sme',
                'country_code' => $data['country_code'],
            ]);

            // The linking firm is either from a specific invitation or a firm-level join
            $firm = $invitation?->accountantFirm ?? $invitingFirm;
            // Referred SMEs (any cabinet entry point) land on Essentiel — that's the
            // plan promised in every referral message ("2 mois offerts sur Essentiel").
            // Only standalone signups (no firm context) stay on Basique.
            $planSlug = $invitation?->recommended_plan ?? ($firm ? 'essentiel' : 'basique');

            $company = Company::create([
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
                'type' => 'sme',
                'country_code' => $data['country_code'],
                'plan' => $planSlug,
            ]);

            $company->users()->attach($user->id, ['role' => CompanyRole::Owner->value]);

            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_slug' => $planSlug,
                'price_paid' => 0,
                'billing_cycle' => 'trial',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(60),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays(60),
                'invited_by_firm_id' => $firm?->id,
            ]);

            if ($firm) {
                AccountantCompany::create([
                    'accountant_firm_id' => $firm->id,
                    'sme_company_id' => $company->id,
                    'started_at' => now(),
                ]);
            }

            if ($invitation) {
                $invitation->update([
                    'status' => 'registering',
                    'sme_company_id' => $company->id,
                ]);
            } elseif ($firm) {
                // Referral-link signup (no pre-invitation): synthesize one so the
                // cabinet still sees this PME in its invitations dashboard, and so
                // the OTP step can flip it to 'accepted' through the normal flow.
                // invitee_company_name stays null until the SME completes
                // company-setup — we don't know it at register time and we don't
                // want to leak the temp Company.name (= user's full name) into the
                // session, which would pre-populate the company-setup form.
                $invitation = PartnerInvitation::create([
                    'accountant_firm_id' => $firm->id,
                    'token' => Str::random(32),
                    'invitee_company_name' => null,
                    'invitee_name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
                    'invitee_phone' => $phone,
                    'recommended_plan' => $planSlug,
                    'channel' => 'link',
                    'status' => 'registering',
                    'sme_company_id' => $company->id,
                    'expires_at' => now()->addDays(30),
                    'link_opened_at' => now(),
                ]);
            }

            // Credit the cabinet with a commission for the current month so the
            // PME shows up in /compta/commissions immediately. Idempotent via
            // firstOrCreate (won't duplicate if the seeder already inserted one).
            if ($firm) {
                Commission::firstOrCreate(
                    [
                        'accountant_firm_id' => $firm->id,
                        'sme_company_id' => $company->id,
                        'period_month' => now()->startOfMonth(),
                    ],
                    [
                        'subscription_id' => $subscription->id,
                        'amount' => CommissionService::calculate(CommissionService::planMonthlyPrice($planSlug)),
                        'status' => 'pending',
                    ]
                );
            }

            $this->otpService->generate($email, 'email_verification');

            return $user;
        });
    }
}
