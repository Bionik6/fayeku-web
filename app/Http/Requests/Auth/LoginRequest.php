<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'profile' => ['required', Rule::in(['sme', 'accountant'])],
            'password' => ['required', 'string'],
        ];

        if ($this->input('profile') === 'sme') {
            $rules['phone'] = ['required', 'string'];
            $rules['country_code'] = ['required', 'string', Rule::in(['SN', 'CI'])];
        }

        if ($this->input('profile') === 'accountant') {
            $rules['email'] = ['required', 'string', 'email', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'profile.required' => 'Veuillez sélectionner votre profil.',
            'profile.in' => 'Le profil sélectionné est invalide.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'country_code.required' => 'Le pays est obligatoire.',
            'email.required' => "L'adresse email est obligatoire.",
            'email.email' => "L'adresse email n'est pas valide.",
            'password.required' => 'Le mot de passe est obligatoire.',
        ];
    }
}
