<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $users = User::with('roles', 'permissions')
            ->orderByName()
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'is_admin' => $user->isAdmin(),
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->permissions->pluck('name'),
            ]);

        $roles = Role::with('permissions')->get()->map(fn ($role) => [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name'),
        ]);

        $permissions = Permission::all()->map(fn ($permission) => [
            'id' => $permission->id,
            'name' => $permission->name,
        ]);

        return Inertia::render('Settings/Index', [
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function updateUserPermissions(User $user): RedirectResponse
    {
        $validated = Request::validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user->syncPermissions($validated['permissions'] ?? []);

        return Redirect::back()->with('success', 'User permissions updated.');
    }

    public function toggleUserAdmin(User $user): RedirectResponse
    {
        if ($user->hasRole('admin')) {
            $user->removeRole('admin');
            $message = 'Admin role removed.';
        } else {
            $user->assignRole('admin');
            $message = 'Admin role assigned.';
        }

        return Redirect::back()->with('success', $message);
    }
}