<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create the main user first
        $mainUser = User::factory()->create([
            'first_name' => 'Max',
            'last_name' => 'Behrens',
            'email' => 'max.behrens@rightglobalgroup.com',
            'password' => 'secret',
        ]);

        // Create additional users for variety
        User::factory(5)->create();

        // Create account with a random user
        $account = Account::create([
            'name' => 'Test Merchant Account',
            'user_id' => User::inRandomOrder()->first()->id,
        ]);

        // Create applications with the account
        $applications = Application::factory(20)
            ->create(['account_id' => $account->id]);

        // Create contacts
        Contact::factory(10)
            ->create(['account_id' => $account->id])
            ->each(function ($contact) use ($applications) {
                $contact->update(['application_id' => $applications->random()->id]);
            });
    }
}