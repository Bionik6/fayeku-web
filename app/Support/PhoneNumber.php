<?php

namespace App\Support;

use App\Services\Auth\AuthService;

/**
 * Normalisation et détection de pays sur des numéros de téléphone
 * en s'appuyant sur la liste large `fayeku.phone_countries` (qui couvre
 * toute l'Afrique de l'Ouest, le Maghreb, etc. — pas seulement les pays
 * où Fayeku opère comme SaaS).
 *
 * À utiliser pour les numéros de téléphone des **clients PME** (envoi WhatsApp,
 * relances, etc.). Pour les numéros des utilisateurs Fayeku eux-mêmes,
 * voir {@see AuthService::normalizePhone()} qui utilise
 * la liste plus restreinte `fayeku.countries` (SN/CI uniquement).
 */
class PhoneNumber
{
    /**
     * Tente de détecter le code pays ISO-2 et le numéro local d'un téléphone
     * fourni en format international (avec ou sans `+`).
     *
     * Si aucun préfixe connu ne correspond, retombe sur SN par défaut.
     *
     * @return array{country_code: string, local_number: string, normalized: string}
     */
    public static function parse(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        foreach (config('fayeku.phone_countries', []) as $code => $country) {
            $prefixDigits = preg_replace('/\D+/', '', $country['prefix'] ?? '') ?? '';

            if ($prefixDigits !== '' && str_starts_with($digits, $prefixDigits)) {
                $localNumber = substr($digits, strlen($prefixDigits));

                return [
                    'country_code' => $code,
                    'local_number' => $localNumber,
                    'normalized' => $country['prefix'].ltrim($localNumber, '0'),
                ];
            }
        }

        return [
            'country_code' => 'SN',
            'local_number' => ltrim($digits, '0'),
            'normalized' => '+221'.ltrim($digits, '0'),
        ];
    }

    /**
     * Normalise un numéro local ou international en format international avec préfixe,
     * en fonction du code pays ISO-2 fourni. Garantit toujours le préfixe en sortie.
     */
    public static function normalize(string $phone, string $countryCode): string
    {
        $prefix = (string) config("fayeku.phone_countries.{$countryCode}.prefix", '');
        $prefixDigits = preg_replace('/\D+/', '', $prefix) ?? '';
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        // L'utilisateur a déjà tapé le préfixe pays.
        if ($prefixDigits !== '' && str_starts_with($digits, $prefixDigits)) {
            return $prefix.ltrim(substr($digits, strlen($prefixDigits)), '0');
        }

        // Format international d'un autre pays (commence par +).
        if (str_starts_with(trim($phone), '+')) {
            return '+'.ltrim($digits, '0');
        }

        // Format local : on préfixe avec le code pays (en strippant le 0 initial).
        return $prefix.ltrim($digits, '0');
    }

    /**
     * Renvoie uniquement les chiffres internationaux (sans `+`) — format requis
     * par `https://wa.me/<digits>` pour ouvrir une conversation WhatsApp.
     */
    public static function digitsForWhatsApp(string $phone, string $countryCode): string
    {
        $normalized = self::normalize($phone, $countryCode);

        return preg_replace('/\D+/', '', $normalized) ?? '';
    }
}
