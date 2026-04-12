<?php

namespace Database\Factories;

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
        ];
    }
}
