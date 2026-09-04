<?php

namespace Database\Factories;

use App\Models\CustomerDeliveryAddress;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerDeliveryAddress>
 */
class CustomerDeliveryAddressFactory extends Factory
{
    protected $model = CustomerDeliveryAddress::class;

    public function definition(): array
    {
        return [
            'customer_id' => User::factory()->customer(),
            'label' => fake()->randomElement(['Home', 'Office', null]),
            'recipient_name' => fake()->name(),
            'phone' => fake()->numerify('9#########'),
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional()->secondaryAddress(),
            'landmark' => fake()->optional()->streetName(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
