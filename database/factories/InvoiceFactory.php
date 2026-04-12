<?php

namespace Database\Factories;

use App\Enums\PME\InvoiceStatus;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\Invoice;
use App\Models\PME\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

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
            'reference' => 'FYK-FAC-'.strtoupper(fake()->unique()->bothify('??????')),
            'currency' => 'XOF',
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

    public function draft(): static
    {
        return $this->state(['status' => InvoiceStatus::Draft]);
    }

    public function withLines(int $count = 2): static
    {
        return $this->afterCreating(function (Invoice $invoice) use ($count) {
            $subtotal = 0;
            $taxAmount = 0;

            for ($i = 0; $i < $count; $i++) {
                $quantity = fake()->numberBetween(1, 10);
                $unitPrice = fake()->numberBetween(5_000, 100_000);
                $taxRate = fake()->randomElement([0, 18]);
                $lineTotal = $quantity * $unitPrice;
                $lineTax = (int) round($lineTotal * $taxRate / 100);

                InvoiceLine::query()->create([
                    'invoice_id' => $invoice->id,
                    'description' => fake()->sentence(3),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
                $taxAmount += $lineTax;
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $subtotal + $taxAmount,
            ]);
        });
    }
}
