<?php

namespace App\Services\Shared;

use App\Models\Auth\Company;
use App\Models\PME\Invoice;
use App\Services\PME\CurrencyService;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class WhatsAppTemplateCatalog
{
    /**
     * Retourne le nom Meta du template (ex. "fayeku_reminder_invoice_due_manual_cordial").
     */
    public function nameFor(string $key): string
    {
        $name = config("whatsapp-templates.{$key}.name");

        if (! is_string($name) || $name === '') {
            throw new InvalidArgumentException("Template WhatsApp inconnu : {$key}");
        }

        return $name;
    }

    /**
     * Rend le corps du template avec les variables substituées. Ce rendu
     * est utilisé pour l'aperçu UI, le message stocké en BDD et le fallback email.
     *
     * @param  array<string, string>  $variables
     */
    public function render(string $key, array $variables): string
    {
        $body = config("whatsapp-templates.{$key}.body");

        if (! is_string($body) || $body === '') {
            throw new InvalidArgumentException("Template WhatsApp inconnu : {$key}");
        }

        return $this->substitute($body, $variables);
    }

    /**
     * Rend l'objet de l'email (fallback) avec les variables substituées.
     *
     * @param  array<string, string>  $variables
     */
    public function renderSubject(string $key, array $variables): string
    {
        $subject = config("whatsapp-templates.{$key}.subject");

        if (! is_string($subject) || $subject === '') {
            return 'Fayeku — notification';
        }

        return $this->substitute($subject, $variables);
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function substitute(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['{'.$name.'}'] = (string) $value;
        }

        return strtr($template, $replacements);
    }

    /**
     * Mappe un offset dunning (jour) vers la clé de template auto correspondante.
     */
    public function autoReminderKeyForOffset(int $offset): ?string
    {
        return match ($offset) {
            -3 => 'reminder_auto_m3',
            0 => 'reminder_auto_0',
            3 => 'reminder_auto_p3',
            7 => 'reminder_auto_p7',
            15 => 'reminder_auto_p15',
            30 => 'reminder_auto_p30',
            60 => 'reminder_auto_p60',
            default => null,
        };
    }

    /**
     * Mappe un ton UI (cordial/ferme/urgent) vers la clé de template manuel correspondante.
     */
    public function manualReminderKeyForTone(string $tone): string
    {
        return match ($tone) {
            'ferme' => 'reminder_manual_firm',
            'urgent' => 'reminder_manual_urgent',
            default => 'reminder_manual_cordial',
        };
    }

    /**
     * Construit le jeu de variables standard pour les templates liés à une facture.
     *
     * @return array<string, string>
     */
    public function invoiceVariables(Invoice $invoice, ?Company $company = null): array
    {
        $company ??= $invoice->company;
        $currency = $invoice->currency ?? 'XOF';

        return [
            'client_name' => $invoice->client?->name ?? 'Cher client',
            'company_name' => $company?->name ?? '',
            'invoice_number' => $invoice->reference ?? '',
            'invoice_amount' => CurrencyService::format((int) $invoice->total, $currency, withLabel: true),
            'invoice_due_date' => $invoice->due_at instanceof CarbonInterface
                ? $invoice->due_at->locale('fr')->translatedFormat('d F Y')
                : '',
            'due_date' => $invoice->due_at instanceof CarbonInterface
                ? $invoice->due_at->locale('fr')->translatedFormat('d F Y')
                : '',
            'sender_signature' => $company?->composeSenderSignature() ?? "L'équipe Fayeku",
        ];
    }

    /**
     * Rend le corps d'une relance manuelle pour l'aperçu (tone = cordial/ferme/urgent).
     */
    public function renderManualReminder(Invoice $invoice, ?Company $company, string $tone): string
    {
        return $this->render(
            $this->manualReminderKeyForTone($tone),
            $this->invoiceVariables($invoice, $company),
        );
    }
}
