<?php

namespace Database\Factories;

use App\Models\Auth\Company;
use Database\Factories\Support\SenegalFaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => SenegalFaker::companyName(),
            'type' => 'sme',
            'plan' => 'basique',
            'country_code' => 'SN',
            'phone' => SenegalFaker::phone(),
            // Par défaut, une Company de test est considérée comme "prête à l'emploi"
            // (onboarding terminé). Les tests qui veulent simuler une PME pré-onboarding
            // doivent override avec `['setup_completed_at' => null]`.
            'setup_completed_at' => now(),
        ];
    }

    public function accountantFirm(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'accountant_firm',
            'invite_code' => strtoupper($this->faker->unique()->lexify('??????')),
        ]);
    }

    public function pendingOnboarding(): static
    {
        return $this->state(fn (array $attributes) => [
            'setup_completed_at' => null,
        ]);
    }
}
