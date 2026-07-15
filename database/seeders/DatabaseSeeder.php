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
            'first_name' => 'G2Pay',
            'last_name' => 'Admin',
            'email' => 'max.behrens@rightglobalgroup.com',
            'password' => 'secret',
        ]);
        $mainUser->assignRole('admin');

        User::factory(1)->create();

        // Create account with a random user. Merchants have no password: send one a
        // login link from their account page to sign in as them.
        $account = Account::create([
            'name' => 'Test Merchant Account',
            'email' => 'test@merchant.com',
            'user_id' => User::inRandomOrder()->first()->id,
        ]);
        $account->assignRole('account');


        $this->command->info('Database seeding completed!');
        $this->command->info('Admin user: max.behrens@rightglobalgroup.com / secret');
        $this->command->info('Test account: test@merchant.com (logs in via emailed link)');
    }
}