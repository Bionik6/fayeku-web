<?php

namespace App\Interfaces\Shared;

interface WhatsAppProviderInterface
{
    public function send(string $phone, string $message): bool;

    /**
     * Envoie un message basé sur un template Meta pré-approuvé.
     *
     * @param  array<string, string>  $bodyParameters  Variables nommées du corps ({{nom}} => valeur)
     * @param  string|null  $urlButtonParameter  Valeur du bouton URL dynamique (ex. "code/pdf")
     */
    public function sendTemplate(
        string $phone,
        string $templateName,
        array $bodyParameters = [],
        ?string $urlButtonParameter = null,
        ?string $language = null,
    ): bool;
}
