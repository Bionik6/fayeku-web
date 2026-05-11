<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = (string) $this->input('email', '');

        $this->merge([
            'email' => $email !== '' ? mb_strtolower(trim($email)) : $email,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:2', 'max:150'],
            'company' => ['nullable', 'string', 'max:200'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'profile' => ['required', 'string', Rule::in(['pme', 'expert', 'autre'])],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Le nom complet est obligatoire.',
            'full_name.min' => 'Le nom doit contenir au moins 2 caractères.',
            'email.required' => "L'adresse e-mail est obligatoire.",
            'email.email' => 'Veuillez entrer une adresse e-mail valide.',
            'profile.required' => 'Veuillez préciser votre profil.',
            'profile.in' => 'Veuillez sélectionner une option valide.',
            'message.required' => 'Le message est obligatoire.',
            'message.min' => 'Le message doit contenir au moins 10 caractères.',
        ];
    }
}
