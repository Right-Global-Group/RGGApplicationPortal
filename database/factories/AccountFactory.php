<?php

namespace Database\Factories;

use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'recipient_name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'mobile' => '07'.$this->faker->numberBetween(100000000, 999999999),
            'first_login_at' => null,
            'link_sent_at' => null,
        ];
    }
}
