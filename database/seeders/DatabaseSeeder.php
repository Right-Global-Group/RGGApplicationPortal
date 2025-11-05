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
        // First, run the permission seeder to create roles
        $this->call(PermissionSeeder::class);

        // Create the main user (YOU) as admin
        $mainUser = User::factory()->create([
            'first_name' => 'Max',
            'last_name' => 'Behrens',
            'email' => 'max.behrens@rightglobalgroup.com',
            'password' => 'secret',
        ]);
        $mainUser->assignRole('admin');

        User::factory(1)->create();

        // Create account with a random user
        $account = Account::create([
            'name' => 'Test Merchant Account',
            'email' => 'test@merchant.com',
            'password' => 'secret123',
            'user_id' => User::inRandomOrder()->first()->id,
            'status' => Account::STATUS_PENDING,
        ]);
        $account->assignRole('account');

        // Create contacts
        Contact::factory(10)
            ->create(['account_id' => $account->id])
            ->each(function ($contact) use ($applications) {
                $contact->update(['application_id' => $applications->random()->id]);
            });

        $this->command->info('Database seeding completed!');
        $this->command->info('Admin user: max.behrens@rightglobalgroup.com / secret');
        $this->command->info('Test account: test@merchant.com / secret123');
    }
}