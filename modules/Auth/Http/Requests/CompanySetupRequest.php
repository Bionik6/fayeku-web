<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanySetupRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:255'],
            'sector' => ['required', 'string', 'max:100', 'not_in:__other__'],
            'ninea' => ['nullable', 'string', 'max:20'],
            'rccm' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => "Le nom de l'entreprise est obligatoire.",
            'sector.required' => "Le secteur d'activité est obligatoire.",
            'sector.not_in' => "Veuillez préciser votre secteur d'activité.",
        ];
    }
}
