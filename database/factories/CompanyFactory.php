<?php

namespace Database\Factories;

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
            'name' => fake()->company(),
            'type' => 'sme',
            'plan' => 'basique',
            'country_code' => 'SN',
            'phone' => '+221'.fake()->unique()->numerify('#########'),
        ];
    }

    public function accountantFirm(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'accountant_firm',
        ]);
    }
}
