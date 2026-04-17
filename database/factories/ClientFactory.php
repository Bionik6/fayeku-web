<?php

namespace Database\Factories;

use App\Enums\PME\DunningStrategy;
use App\Models\PME\Client;
use Database\Factories\Support\SenegalFaker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = SenegalFaker::firstName();
        $lastName = SenegalFaker::lastName();

        return [
            'name' => SenegalFaker::companyName(),
            'phone' => SenegalFaker::phone(),
            'email' => SenegalFaker::email($firstName, $lastName),
            'address' => SenegalFaker::address(),
            'tax_id' => 'SN'.strtoupper(fake()->numerify('##########')),
            'dunning_strategy' => DunningStrategy::Standard,
        ];
    }

    public function soft(): static
    {
        return $this->state(['dunning_strategy' => DunningStrategy::Soft]);
    }

    public function strict(): static
    {
        return $this->state(['dunning_strategy' => DunningStrategy::Strict]);
    }

    public function noDunning(): static
    {
        return $this->state(['dunning_strategy' => DunningStrategy::None]);
    }
}
