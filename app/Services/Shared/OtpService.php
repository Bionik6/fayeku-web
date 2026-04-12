<?php

namespace App\Services\Shared;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Interfaces\Shared\SmsProviderInterface;

class OtpService
{
    public function __construct(private SmsProviderInterface $sms) {}

    public function generate(string $phone, string $purpose = 'verification'): string
    {
        $code = (string) random_int(100000, 999999);

        DB::table('otp_codes')->insert([
            'id' => (string) Str::ulid(),
            'phone' => $phone,
            'code' => hash('sha256', $code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes((int) config('fayeku.otp_expiry_minutes', 10)),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sms->send($phone, "Votre code Fayeku : {$code}");

        return $code;
    }

    public function verify(string $phone, string $code, string $purpose = 'verification'): bool
    {
        $bypassCode = config('fayeku.otp_bypass_code');

        if ($bypassCode && app()->environment('local') && $code === $bypassCode) {
            return true;
        }

        $record = DB::table('otp_codes')
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->where('expires_at', '>=', now())
            ->where('attempts', '<', config('fayeku.otp_max_attempts', 3))
            ->latest('created_at')
            ->first();

        if (! $record) {
            return false;
        }

        if (! hash_equals($record->code, hash('sha256', $code))) {
            DB::table('otp_codes')->where('id', $record->id)->increment('attempts');

            return false;
        }

        DB::table('otp_codes')->where('id', $record->id)
            ->update(['used_at' => now(), 'updated_at' => now()]);

        return true;
    }

    public function canResend(string $phone, string $purpose = 'verification'): bool
    {
        $lastOtp = DB::table('otp_codes')
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->latest('created_at')
            ->first();

        if (! $lastOtp) {
            return true;
        }

        return $lastOtp->created_at <= now()->subSeconds(60)->toDateTimeString();
    }
}
