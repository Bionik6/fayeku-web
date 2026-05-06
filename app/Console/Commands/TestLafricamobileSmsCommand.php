<?php

namespace App\Console\Commands;

use App\Services\Shared\LafricamobileSmsProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lafricamobile:test-sms
    {phone : Numero destinataire au format international (ex. +221771234567)}
    {--message=Test SMS Fayeku via Lafricamobile : Message libre a envoyer}')]
#[Description('Envoie un SMS de test via Lafricamobile (LAMPUSH) pour valider les credentials et le sender FAYEKU.')]
class TestLafricamobileSmsCommand extends Command
{
    public function handle(): int
    {
        $missing = [];
        foreach (['account_id', 'password', 'sender'] as $key) {
            if (empty(config("services.lafricamobile_sms.$key"))) {
                $missing[] = 'LAFRICAMOBILE_SMS_'.strtoupper($key);
            }
        }

        if ($missing !== []) {
            $this->error('Credentials manquants dans .env : '.implode(', ', $missing));
            $this->line('  -> Remplis ces clefs puis lance : php artisan config:clear');

            return self::FAILURE;
        }

        $phone = (string) $this->argument('phone');
        $message = (string) $this->option('message');

        $provider = new LafricamobileSmsProvider(
            baseUrl: config('services.lafricamobile_sms.base_url'),
            accountId: config('services.lafricamobile_sms.account_id'),
            password: config('services.lafricamobile_sms.password'),
            sender: config('services.lafricamobile_sms.sender'),
            callbackUrl: config('services.lafricamobile_sms.callback_url'),
        );

        $this->info(sprintf(
            'Envoi SMS Lafricamobile (sender=%s) vers %s ...',
            config('services.lafricamobile_sms.sender'),
            $phone,
        ));

        $provider->send($phone, $message);

        $result = $provider->lastResult();

        if ($result === null) {
            $this->warn('Aucune reponse Lafricamobile capturee.');

            return self::FAILURE;
        }

        if ($result['ok']) {
            $this->info(sprintf('Lafricamobile a accepte la requete (HTTP %d).', $result['status']));

            if ($result['body']) {
                $this->line('  Reponse : '.$result['body']);
            }

            return self::SUCCESS;
        }

        $this->error(sprintf('Lafricamobile a refuse l\'envoi (HTTP %s).', $result['status'] ?? 'n/a'));

        if ($result['body']) {
            $this->line('  Reponse : '.$result['body']);
        }

        return self::FAILURE;
    }
}
