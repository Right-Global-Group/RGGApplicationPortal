<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UsersController extends Controller
{
    public function index(): Response
    {
        $query = User::query();

        // Search filter for name
        if ($search = Request::input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Search filter for email
        if ($emailSearch = Request::input('email_search')) {
            $query->where('email', 'like', "%{$emailSearch}%");
        }

        // Date range filter
        if ($dateFrom = Request::input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = Request::input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Deleted filter
        if ($deleted = Request::input('deleted')) {
            if ($deleted === 'with') {
                $query->withTrashed();
            } elseif ($deleted === 'only') {
                $query->onlyTrashed();
            }
        }

        $users = $query
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'photo' => $user->photo_path ? URL::route('users.photo', ['user' => $user->id]) : null,
                'deleted_at' => $user->deleted_at,
                'created_at' => $user->created_at?->format('Y-m-d H:i'),
                'updated_at' => $user->updated_at?->format('Y-m-d H:i'),
            ]);

        return Inertia::render('Users/Index', [
            'filters' => Request::only(['search', 'email_search', 'date_from', 'date_to', 'deleted']),
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create');
    }

    public function store(): RedirectResponse
    {
        Request::validate([
            'first_name' => ['required', 'max:50'],
            'last_name' => ['required', 'max:50'],
            'email' => ['required', 'max:50', 'email', Rule::unique('users')],
            'password' => ['nullable'],
            'photo' => ['nullable', 'image', 'max:5120'], // 5MB max
        ]);

        $photoPath = null;
        if (Request::file('photo')) {
            $file = Request::file('photo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $photoPath = $file->storeAs('users', $filename, 'private');
        }

        User::create([
            'first_name' => Request::get('first_name'),
            'last_name'  => Request::get('last_name'),
            'email'      => Request::get('email'),
            'password'   => Request::get('password'),
            'photo_path' => $photoPath,
        ]);        

        return Redirect::route('users')->with('success', 'User created.');
    }

    public function edit(User $user): Response
    {
        $applications = $user->applications()
            ->with('account')
            ->get(['id', 'name', 'account_id', 'created_at']);

        $accounts = $user->accounts()
            ->orderBy('name')
            ->get(['id', 'name', 'created_at']);
    
        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'photo' => $user->photo_path ? URL::route('users.photo', ['user' => $user->id]) : null,
                'deleted_at' => $user->deleted_at,
            ],
            'applications' => $applications->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'account_name' => $a->account?->name,
                'created_at' => $a->created_at,
            ]),
            'accounts' => $accounts->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'created_at' => $a->created_at,
            ]),
        ]);
    }    

    public function update(User $user): RedirectResponse
    {
        if (App::environment('demo') && $user->isDemoUser()) {
            return Redirect::back()->with('error', 'Updating the demo user is not allowed.');
        }

        Request::validate([
            'first_name' => ['required', 'max:50'],
            'last_name' => ['required', 'max:50'],
            'email' => ['required', 'max:50', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable'],
            'photo' => ['nullable', 'image', 'max:5120'], // 5MB max
        ]);

        $user->update(Request::only('first_name', 'last_name', 'email'));

        if (Request::file('photo')) {
            // Delete old photo if exists
            if ($user->photo_path) {
                Storage::disk('private')->delete($user->photo_path);
            }
            
            // Store new photo
            $file = Request::file('photo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $photoPath = $file->storeAs('users', $filename, 'private');
            
            $user->update(['photo_path' => $photoPath]);
        }

        if (Request::get('password')) {
            $user->update(['password' => Request::get('password')]);
        }

        return Redirect::back()->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (App::environment('demo') && $user->isDemoUser()) {
            return Redirect::back()->with('error', 'Deleting the demo user is not allowed.');
        }

        // Delete photo if exists
        if ($user->photo_path) {
            Storage::disk('private')->delete($user->photo_path);
        }

        $user->delete();

        return Redirect::back()->with('success', 'User deleted.');
    }

    public function restore(User $user): RedirectResponse
    {
        $user->restore();

        return Redirect::back()->with('success', 'User restored.');
    }

    public function showPhoto(User $user)
    {
        // Check permissions - allow viewing if:
        // 1. User is viewing their own photo
        // 2. User is admin
        // 3. User manages accounts that this user owns
        // 4. Account user viewing the photo of a user who manages them
        
        if (auth()->guard('web')->check()) {
            $authUser = auth()->guard('web')->user();
            
            // Allow if viewing own photo or is admin
            if ($authUser->id === $user->id || $authUser->isAdmin()) {
                // OK
            } else {
                // Check if auth user manages any accounts owned by the viewed user
                $hasAccess = $user->accounts()
                    ->where('user_id', $authUser->id)
                    ->exists();
                
                if (!$hasAccess) {
                    abort(403);
                }
            }
        } elseif (auth()->guard('account')->check()) {
            // Accounts can view photos of users who manage them
            $accountId = auth()->guard('account')->id();
            $hasAccess = $user->accounts()
                ->where('id', $accountId)
                ->exists();
            
            if (!$hasAccess) {
                abort(403);
            }
        } else {
            abort(403);
        }

        // Check if user has a photo
        if (!$user->photo_path) {
            abort(404);
        }

        // Check if file exists
        if (!Storage::disk('private')->exists($user->photo_path)) {
            abort(404);
        }

        // Get the file
        $file = Storage::disk('private')->get($user->photo_path);
        $mimeType = Storage::disk('private')->mimeType($user->photo_path);

        return response($file, 200)
            ->header('Content-Type', $mimeType)
            ->header('Cache-Control', 'public, max-age=31536000'); // Cache for 1 year
    }
}