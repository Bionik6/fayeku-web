<?php

namespace App\Http\Requests;

use App\Services\Auth\AuthService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountantJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise téléphone (indicatif + chiffres, sans espace) et email
     * (lowercase, trim) avant validation pour que les règles `unique`
     * pointent toujours sur la forme canonique stockée en base.
     */
    protected function prepareForValidation(): void
    {
        $countryCode = (string) $this->input('country_code', '');
        $phone = (string) $this->input('phone', '');
        $email = (string) $this->input('email', '');

        $this->merge([
            'phone' => $countryCode !== '' && $phone !== ''
                ? AuthService::normalizePhone($phone, $countryCode)
                : $phone,
            'email' => $email !== '' ? mb_strtolower(trim($email)) : $email,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Une demande déjà rejetée ne bloque pas une nouvelle candidature :
        // on autorise une re-soumission après un refus côté équipe.
        $leadActiveStatusFilter = fn ($rule) => $rule->whereNot('status', 'rejected');

        return [
            'first_name' => ['required', 'string', 'min:2', 'max:100'],
            'last_name' => ['required', 'string', 'min:2', 'max:100'],
            'firm' => ['required', 'string', 'min:2', 'max:200'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email'),
                $leadActiveStatusFilter(Rule::unique('accountant_leads', 'email')),
            ],
            'country_code' => ['required', 'string', Rule::in(['SN', 'CI'])],
            'phone' => [
                'required', 'string', 'min:8', 'max:20',
                Rule::unique('users', 'phone'),
                $leadActiveStatusFilter(Rule::unique('accountant_leads', 'phone')),
            ],
            'region' => ['required', 'string', Rule::in(['Dakar', 'Thiès', 'Saint-Louis', 'Diourbel', 'Kaolack', 'Ziguinchor', 'Tambacounda', 'Autre région'])],
            'portfolio_size' => ['required', 'string', Rule::in(['1 à 20 dossiers', '21 à 50 dossiers', '51 à 100 dossiers', '101 à 250 dossiers', '250+ dossiers'])],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'first_name.min' => 'Le prénom doit contenir au moins 2 caractères.',
            'last_name.required' => 'Le nom est obligatoire.',
            'last_name.min' => 'Le nom doit contenir au moins 2 caractères.',
            'firm.required' => 'Le nom du cabinet est obligatoire.',
            'firm.min' => 'Le nom du cabinet doit contenir au moins 2 caractères.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.email' => 'Veuillez entrer une adresse e-mail valide.',
            'email.unique' => 'Cette adresse e-mail est déjà connue de Fayeku. Si vous avez déjà un compte ou une demande en cours, contactez-nous.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.min' => 'Le numéro de téléphone semble invalide.',
            'phone.unique' => 'Ce numéro est déjà connu de Fayeku. Si vous avez déjà un compte ou une demande en cours, contactez-nous.',
            'region.required' => 'Veuillez sélectionner une région.',
            'region.in' => 'Veuillez sélectionner une région valide.',
            'portfolio_size.required' => 'Veuillez indiquer le nombre de dossiers.',
            'portfolio_size.in' => 'Veuillez sélectionner une valeur valide.',
            'message.required' => 'Veuillez indiquer ce que vous attendez de Fayeku.',
            'message.min' => 'Ce champ doit contenir au moins 10 caractères.',
        ];
    }
}
