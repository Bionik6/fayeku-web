<?php

namespace App\Console\Commands;

use App\Interfaces\Shared\SmsProviderInterface;
use App\Services\Shared\OrangeSmsProvider;
use App\Services\Shared\OtpService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orange:test-sms
    {phone : Numero destinataire au format international (ex. +221771234567)}
    {--message=Test SMS Fayeku via Orange : Message libre a envoyer (ignore avec --otp)}
    {--otp : Genere un vrai code OTP via OtpService et l\'envoie via le canal configure (test bout en bout)}')]
#[Description('Envoie un SMS de test via Orange SMS API pour valider les credentials, ou un OTP complet via OtpService avec --otp.')]
class TestOrangeSmsCommand extends Command
{
    public function handle(): int
    {
        $missing = [];
        foreach (['client_id', 'client_secret', 'sender_address'] as $key) {
            if (empty(config("services.orange_sms.$key"))) {
                $missing[] = 'ORANGE_SMS_'.strtoupper($key);
            }
        }

        if ($missing !== []) {
            $this->error('Credentials manquants dans .env : '.implode(', ', $missing));
            $this->line('  -> Remplis ces clefs puis lance : php artisan config:clear');

            return self::FAILURE;
        }

        $phone = (string) $this->argument('phone');

        if ($this->option('otp')) {
            return $this->sendOtp($phone);
        }

        return $this->sendRawSms($phone);
    }

    private function sendOtp(string $phone): int
    {
        $channel = config('fayeku.otp_channel');

        if ($channel !== 'sms') {
            $this->error(sprintf(
                'OTP_CHANNEL doit valoir "sms" pour ce test (actuel : %s).',
                $channel ?: 'non defini',
            ));
            $this->line('  -> Mets OTP_CHANNEL=sms dans .env puis lance : php artisan config:clear');

            return self::FAILURE;
        }

        $this->info("Generation OTP et envoi via Orange SMS vers {$phone} ...");

        $code = app(OtpService::class)->generate($phone, 'verification');

        $this->info('OTP genere et stocke dans otp_codes (envoi delegue au canal SMS configure).');

        if (! app()->isProduction()) {
            $this->line("  Code (debug local uniquement) : {$code}");
        }

        return $this->reportOrangeResponse();
    }

    private function sendRawSms(string $phone): int
    {
        $provider = app(SmsProviderInterface::class);

        if (! $provider instanceof OrangeSmsProvider) {
            $this->error(sprintf(
                'Le provider actif est %s et non OrangeSmsProvider. Verifie APP_ENV (%s) et les credentials Orange.',
                $provider::class,
                app()->environment(),
            ));

            return self::FAILURE;
        }

        $message = (string) $this->option('message');

        $this->info("Envoi SMS Orange vers {$phone} ...");

        $provider->send($phone, $message);

        return $this->reportOrangeResponse();
    }

    private function reportOrangeResponse(): int
    {
        $provider = app(SmsProviderInterface::class);

        if (! $provider instanceof OrangeSmsProvider) {
            $this->warn('Provider actif non-Orange : aucune reponse a afficher.');

            return self::SUCCESS;
        }

        $result = $provider->lastResult();

        if ($result === null) {
            $this->warn('Aucune reponse Orange capturee (envoi peut-etre court-circuite).');

            return self::FAILURE;
        }

        if ($result['ok']) {
            $this->info(sprintf('Orange a accepte la requete (HTTP %d).', $result['status']));

            if ($result['resourceURL']) {
                $this->line('  resourceURL : '.$result['resourceURL']);
                $this->line('  -> Communique cet identifiant au support Orange si le SMS n\'arrive pas.');
            }

            return self::SUCCESS;
        }

        $this->error(sprintf('Orange a refuse l\'envoi (HTTP %s).', $result['status'] ?? 'n/a'));

        if ($result['body']) {
            $this->line('  Reponse : '.$result['body']);
        }

        return self::FAILURE;
    }
}
