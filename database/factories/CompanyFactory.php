<?php

namespace Database\Factories;

use Database\Factories\Support\SenegalFaker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\Models\Company;

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
        ];
    }

    public function accountantFirm(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'accountant_firm',
            'invite_code' => strtoupper($this->faker->unique()->lexify('??????')),
        ]);
    }
}
