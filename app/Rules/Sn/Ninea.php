<?php

namespace App\Rules\Sn;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation souple du NINEA sénégalais.
 *
 * Format observé : 7 à 9 chiffres suivis d'un suffixe alphanumérique court
 * (souvent 2 ou 3 caractères type "2A2"). On reste tolérant pour ne pas
 * bloquer un utilisateur dont le numéro est valide mais formaté différemment.
 */
class Ninea implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Le NINEA doit être une chaîne de caractères.');

            return;
        }

        $normalized = strtoupper(preg_replace('/\s+/', '', $value) ?? '');

        if (! preg_match('/^\d{7,9}[A-Z0-9]{1,4}$/', $normalized)) {
            $fail('Format NINEA invalide. Attendu : 7 à 9 chiffres suivis de 1 à 4 caractères (ex. : 0051234562A2).');
        }
    }
}
