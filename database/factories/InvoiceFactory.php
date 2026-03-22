<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\Models\Company;
use Modules\PME\Clients\Models\Client;
use Modules\PME\Invoicing\Enums\InvoiceStatus;
use Modules\PME\Invoicing\Models\Invoice;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(50_000, 500_000);
        $taxAmount = (int) round($subtotal * 0.18);

        return [
            'company_id' => Company::factory(),
            'client_id' => null,
            'reference' => 'FAC-'.fake()->unique()->numerify('####'),
            'status' => InvoiceStatus::Draft,
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
            'paid_at' => null,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'amount_paid' => 0,
        ];
    }

    public function forCompany(Company|array $company): static
    {
        $attributes = $company instanceof Company
            ? ['company_id' => $company->id]
            : ['company_id' => Company::factory()->create($company)->id];

        return $this->state($attributes);
    }

    public function withClient(Client $client): static
    {
        return $this->state(['client_id' => $client->id]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
            'amount_paid' => $attributes['total'],
        ]);
    }

    public function sent(): static
    {
        return $this->state(['status' => InvoiceStatus::Sent]);
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => InvoiceStatus::Overdue,
            'due_at' => now()->subDays(10),
        ]);
    }
}
