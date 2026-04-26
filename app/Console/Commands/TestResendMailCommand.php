<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('resend:test-mail
    {email : Adresse e-mail du destinataire}
    {--subject=Test Fayeku via Resend : Sujet du message}')]
#[Description('Envoie un e-mail de test via Resend pour valider la configuration.')]
class TestResendMailCommand extends Command
{
    public function handle(): int
    {
        if (empty(config('services.resend.key'))) {
            $this->error('RESEND_API_KEY manquant dans .env.');
            $this->line('  -> Remplis cette clef puis lance : php artisan config:clear');

            return self::FAILURE;
        }

        if (config('mail.default') !== 'resend') {
            $this->warn('MAIL_MAILER actuel : '.config('mail.default').'. Forçage sur resend pour ce test.');
        }

        $to = (string) $this->argument('email');
        $subject = (string) $this->option('subject');
        $from = config('mail.from.address');

        $this->info("Envoi e-mail vers {$to} depuis {$from} ...");

        try {
            Mail::mailer('resend')
                ->raw('Bonjour, ceci est un e-mail de test envoyé via Resend depuis Fayeku.', function ($message) use ($to, $subject): void {
                    $message->to($to)->subject($subject);
                });

            $this->info('E-mail envoyé avec succès.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Échec de l\'envoi : '.$e->getMessage());
            $this->line('  -> Consulte storage/logs/laravel.log pour plus de détails.');

            return self::FAILURE;
        }
    }
}
