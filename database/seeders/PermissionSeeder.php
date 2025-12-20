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
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
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
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'account']
            );
        }

        // Create roles for web guard
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        // Sync all permissions to admin (this will update existing role)
        $adminRole->syncPermissions(Permission::where('guard_name', 'web')->get());

        // Sync limited permissions to regular users (this will update existing role)
        $userRole->syncPermissions([
            'view accounts',
            'view applications',
            'create applications',
            'edit applications',
            // NOTE: 'view invoices' is intentionally excluded for regular users
        ]);

        // Create role for account guard
        $accountRole = Role::firstOrCreate(['name' => 'account', 'guard_name' => 'account']);
        $accountRole->syncPermissions(Permission::where('guard_name', 'account')->get());

        $this->command->info('Permissions and roles created/updated successfully!');
        $this->command->info('Regular users do NOT have "view invoices" permission.');
        $this->command->info('Only admin users can view invoices.');
    }
}