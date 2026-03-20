<?php

namespace Modules\Auth\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Models\Company;
use Modules\Auth\Models\Subscription;
use Modules\Shared\Models\User;
use Modules\Shared\Services\OtpService;

class AuthService
{
    public function __construct(private OtpService $otpService) {}

    public static function normalizePhone(string $phone, string $countryCode): string
    {
        $prefix = config("fayeku.countries.{$countryCode}.prefix", '');

        return $prefix.ltrim($phone, '0');
    }

    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $phone = self::normalizePhone($data['phone'], $data['country_code']);

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'phone' => $phone,
                'password' => $data['password'],
                'profile_type' => $data['profile_type'],
                'country_code' => $data['country_code'],
            ]);

            $type = $data['profile_type'] === 'sme' ? 'sme' : 'accountant_firm';
            $company = Company::create([
                'name' => $data['company_name'],
                'type' => $type,
                'country_code' => $data['country_code'],
                'plan' => 'basique',
            ]);

            $company->users()->attach($user->id, ['role' => 'owner']);

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
