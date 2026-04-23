<?php

namespace App\Console\Commands;

use App\Interfaces\Shared\SmsProviderInterface;
use App\Services\Shared\OrangeSmsProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orange:test-sms
    {phone : Numero destinataire au format international (ex. +221771234567)}
    {--message=Votre code Fayeku : 123456 : Message a envoyer}')]
#[Description('Envoie un SMS de test via Orange SMS API (Senegal) pour valider les credentials.')]
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

        $provider = app(SmsProviderInterface::class);

        if (! $provider instanceof OrangeSmsProvider) {
            $this->error(sprintf(
                'Le provider actif est %s et non OrangeSmsProvider. Verifie APP_ENV (%s) et les credentials Orange.',
                $provider::class,
                app()->environment(),
            ));

            return self::FAILURE;
        }

        $phone = (string) $this->argument('phone');
        $message = (string) $this->option('message');

        $this->info("Envoi SMS Orange vers {$phone} ...");

        $ok = $provider->send($phone, $message);

        if ($ok) {
            $this->info('SMS envoye avec succes.');

            return self::SUCCESS;
        }

        $this->error('Echec de l\'envoi. Consulte storage/logs/laravel.log (recherche "Orange SMS").');

        return self::FAILURE;
    }
}
