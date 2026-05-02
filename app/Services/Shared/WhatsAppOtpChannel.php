<?php

namespace App\Services\Shared;

use App\Interfaces\Shared\OtpChannelInterface;
use App\Interfaces\Shared\WhatsAppProviderInterface;

class WhatsAppOtpChannel implements OtpChannelInterface
{
    public const TEMPLATE_KEY = 'otp_code';

    public function __construct(
        private readonly WhatsAppProviderInterface $provider,
        private readonly WhatsAppTemplateCatalog $catalog,
    ) {}

    public function send(string $identifier, string $code): bool
    {
        // Les templates catégorie AUTHENTICATION de Meta imposent :
        //   - body = une unique variable positionnelle {{1}} (le code)
        //   - bouton "Copy code" qui reçoit la même valeur (le code) en paramètre
        //   - footer « sécurité » + « expiration » pilotés par options Meta, pas par variables
        return $this->provider->sendTemplate(
            phone: $identifier,
            templateName: $this->catalog->nameFor(self::TEMPLATE_KEY),
            bodyParameters: [$code],
            urlButtonParameter: $code,
        );
    }
}
