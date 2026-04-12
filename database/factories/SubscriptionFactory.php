<?php

namespace Database\Factories;

use App\Models\Auth\Company;
use App\Models\Auth\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'plan_slug' => 'basique',
            'price_paid' => 0,
            'billing_cycle' => 'trial',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(60),
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(60),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'trial_ends_at' => null,
        ]);
    }
}
