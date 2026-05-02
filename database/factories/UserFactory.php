<?php

namespace Database\Factories;

use App\Models\Shared\User;
use Database\Factories\Support\SenegalFaker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => SenegalFaker::firstName(),
            'last_name' => SenegalFaker::lastName(),
            'phone' => SenegalFaker::phone(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'profile_type' => 'sme',
            'country_code' => 'SN',
            'is_active' => true,
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function unverifiedPhone(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }

    public function accountantFirm(): static
    {
        return $this->state(fn (array $attributes) => [
            'profile_type' => 'accountant_firm',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
