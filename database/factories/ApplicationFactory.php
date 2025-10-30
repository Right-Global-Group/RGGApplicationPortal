<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::inRandomOrder()->first()?->id,
            'name' => $this->faker->company(),
            'email' => $this->faker->companyEmail(),
            'phone' => '07' . $this->faker->numberBetween(100000000, 999999999),
        ];
    }
}