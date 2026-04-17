<?php

namespace Database\Factories;

use App\Enums\PME\PaymentMethod;
use App\Models\PME\Invoice;
use App\Models\PME\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => fake()->numberBetween(10_000, 200_000),
            'paid_at' => now(),
            'method' => PaymentMethod::Transfer,
            'reference' => null,
            'notes' => null,
            'recorded_by' => null,
        ];
    }
}
