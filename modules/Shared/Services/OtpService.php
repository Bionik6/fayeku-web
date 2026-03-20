<?php

namespace Modules\Shared\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Shared\Interfaces\SmsProviderInterface;

class OtpService
{
    public function __construct(private SmsProviderInterface $sms) {}

    public function generate(string $phone): string
    {
        $code = (string) random_int(100000, 999999);

        DB::table('otp_codes')->insert([
            'id' => (string) Str::ulid(),
            'phone' => $phone,
            'code' => hash('sha256', $code),
            'expires_at' => now()->addMinutes((int) config('fayeku.otp_expiry_minutes', 10)),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->sms->send($phone, "Votre code Fayeku : {$code}");

        return $code;
    }

    public function verify(string $phone, string $code): bool
    {
        $record = DB::table('otp_codes')
            ->where('phone', $phone)
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
}
