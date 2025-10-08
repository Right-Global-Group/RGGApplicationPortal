<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->tollFreePhoneNumber(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'region' => $this->faker->state(),
            'country' => 'US',
            'postal_code' => $this->faker->postcode(),
        ];
    }
}
