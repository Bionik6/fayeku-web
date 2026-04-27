<?php

namespace App\Http\Requests\Auth\Sme;

use App\Models\Compta\PartnerInvitation;
use App\Models\Shared\User;
use App\Services\Auth\AuthService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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

    /**
     * SME registration always sets profile_type to 'sme'.
     *
     * @param  array<string>|string|null  $key
     * @return mixed
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        $data['profile_type'] = 'sme';

        if ($key === null) {
            return $data;
        }

        return data_get($data, $key, $default);
    }
}
