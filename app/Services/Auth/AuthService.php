<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Auth\AccountantCompany;
use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use App\Models\Compta\PartnerInvitation;
use App\Models\Shared\User;
use App\Services\Shared\OtpService;

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

    public function register(array $data, ?PartnerInvitation $invitation = null, ?Company $invitingFirm = null): User
    {
        return DB::transaction(function () use ($data, $invitation, $invitingFirm) {
            $phone = self::normalizePhone($data['phone'], $data['country_code']);

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $phone,
                'password' => $data['password'],
                'profile_type' => $data['profile_type'],
                'country_code' => $data['country_code'],
            ]);

            // The linking firm is either from a specific invitation or a firm-level join
            $firm = $invitation?->accountantFirm ?? $invitingFirm;
            $planSlug = $invitation?->recommended_plan ?? 'basique';
            $type = $data['profile_type'] === 'sme' ? 'sme' : 'accountant_firm';

            $company = Company::create([
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
                'type' => $type,
                'country_code' => $data['country_code'],
                'plan' => $planSlug,
            ]);

            $company->users()->attach($user->id, ['role' => 'owner']);

            Subscription::create([
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
            }

            $this->otpService->generate($phone);

            return $user;
        });
    }

    public function requestPasswordReset(string $phone): void
    {
        $user = User::where('phone', $phone)->first();

        if (! $user) {
            return;
        }

        $this->otpService->generate($phone, 'password_reset');
    }

    public function resetPassword(string $phone, string $code, string $newPassword): bool
    {
        if (! $this->otpService->verify($phone, $code, 'password_reset')) {
            return false;
        }

        $user = User::where('phone', $phone)->first();

        if (! $user) {
            return false;
        }

        $user->forceFill([
            'password' => Hash::make($newPassword),
            'phone_verified_at' => $user->phone_verified_at ?? now(),
        ])->save();

        return true;
    }
}
