<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\Account;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions for web guard (Users)
        $userPermissions = [
            'view accounts',
            'create accounts',
            'edit accounts',
            'delete accounts',
            'view applications',
            'create applications',
            'edit applications',
            'delete applications',
            'view users',
            'create users',
            'edit users',
            'delete users',
            'manage settings',
            'view invoices',
        ];

        foreach ($userPermissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create permissions for account guard (Accounts)
        $accountPermissions = [
            'view own account',
            'edit own account',
            'view own applications',
            'create own applications',
            'edit own applications',
        ];

        foreach ($accountPermissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'account']);
        }

        // Create roles for web guard
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $userRole = Role::create(['name' => 'user', 'guard_name' => 'web']);

        // Assign all permissions to admin
        $adminRole->givePermissionTo(Permission::where('guard_name', 'web')->get());

        // Assign limited permissions to regular users
        $userRole->givePermissionTo([
            'view accounts',
            'view applications',
            'create applications',
            'edit applications',
        ]);

        // Create role for account guard
        $accountRole = Role::create(['name' => 'account', 'guard_name' => 'account']);
        $accountRole->givePermissionTo(Permission::where('guard_name', 'account')->get());

        $this->command->info('Permissions and roles created successfully!');
        $this->command->info('Note: Roles will be assigned during user/account creation.');
    }
}