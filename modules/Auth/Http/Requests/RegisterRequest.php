<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Auth\Services\AuthService;
use Modules\Compta\Partnership\Models\PartnerInvitation;
use Modules\Shared\Models\User;

class RegisterRequest extends FormRequest
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
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'profile_type' => ['required', 'string', Rule::in(['sme', 'accountant_firm'])],
            'country_code' => ['required', 'string', Rule::in(['SN', 'CI'])],
            'invitation_token' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'Le prénom est obligatoire.',
            'last_name.required' => 'Le nom est obligatoire.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
            'profile_type.required' => 'Le type de profil est obligatoire.',
            'profile_type.in' => 'Le type de profil sélectionné est invalide.',
            'country_code.required' => 'Le pays est obligatoire.',
            'country_code.in' => 'Le pays sélectionné est invalide.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->hasAny(['phone', 'country_code'])) {
                return;
            }

            $normalizedPhone = AuthService::normalizePhone(
                $this->input('phone'),
                $this->input('country_code')
            );

            if (User::where('phone', $normalizedPhone)->exists()) {
                $validator->errors()->add('phone', 'Ce numéro de téléphone est déjà utilisé.');
            }

            $token = $this->input('invitation_token');

            if ($token) {
                $invitation = PartnerInvitation::where('token', $token)
                    ->where('status', 'pending')
                    ->first();

                if (! $invitation || $invitation->expires_at?->isPast()) {
                    $validator->errors()->add('invitation_token', 'Cette invitation est invalide ou expirée.');
                }
            }
        });
    }
}
