<?php

namespace Database\Factories;

use App\Enums\PME\ProposalDocumentStatus;
use App\Enums\PME\ProposalDocumentType;
use App\Models\Auth\Company;
use App\Models\PME\Client;
use App\Models\PME\ProposalDocument;
use App\Models\PME\ProposalDocumentLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProposalDocument>
 */
class ProposalDocumentFactory extends Factory
{
    protected $model = ProposalDocument::class;

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
            'type' => ProposalDocumentType::Quote,
            'reference' => 'FYK-DEV-'.strtoupper(fake()->unique()->bothify('??????')),
            'currency' => 'XOF',
            'status' => ProposalDocumentStatus::Draft,
            'issued_at' => now(),
            'valid_until' => now()->addDays(30),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
            'discount' => 0,
            'discount_type' => 'percent',
        ];
    }

    public function quote(): static
    {
        return $this->state(fn () => [
            'type' => ProposalDocumentType::Quote,
            'reference' => 'FYK-DEV-'.strtoupper(fake()->unique()->bothify('??????')),
        ]);
    }

    public function proforma(): static
    {
        return $this->state(fn () => [
            'type' => ProposalDocumentType::Proforma,
            'reference' => 'FYK-PRO-'.strtoupper(fake()->unique()->bothify('??????')),
        ]);
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

    public function sent(): static
    {
        return $this->state(['status' => ProposalDocumentStatus::Sent]);
    }

    public function accepted(): static
    {
        return $this->quote()->state(['status' => ProposalDocumentStatus::Accepted]);
    }

    public function poReceived(): static
    {
        return $this->proforma()->state(['status' => ProposalDocumentStatus::PoReceived]);
    }

    public function converted(): static
    {
        return $this->proforma()->state(['status' => ProposalDocumentStatus::Converted]);
    }

    public function declined(): static
    {
        return $this->state(['status' => ProposalDocumentStatus::Declined]);
    }

    public function expired(): static
    {
        return $this->state(['status' => ProposalDocumentStatus::Expired]);
    }

    public function withLines(int $count = 2): static
    {
        return $this->afterCreating(function (ProposalDocument $document) use ($count) {
            $subtotal = 0;
            $taxAmount = 0;

            for ($i = 0; $i < $count; $i++) {
                $quantity = fake()->numberBetween(1, 10);
                $unitPrice = fake()->numberBetween(5_000, 100_000);
                $taxRate = fake()->randomElement([0, 18]);
                $lineTotal = $quantity * $unitPrice;
                $lineTax = (int) round($lineTotal * $taxRate / 100);

                ProposalDocumentLine::query()->create([
                    'proposal_document_id' => $document->id,
                    'description' => fake()->sentence(3),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
                $taxAmount += $lineTax;
            }

            $document->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $subtotal + $taxAmount,
            ]);
        });
    }
}
