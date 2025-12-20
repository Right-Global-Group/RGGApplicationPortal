<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        // Check if user guard is authenticated
        if (auth()->guard('web')->check()) {
            $user = auth()->guard('web')->user();
            
            // Use Spatie's hasRole instead of isAdmin()
            if (!$user->hasRole($role)) {
                abort(403, 'Unauthorized action.');
            }
        }
        // Check if account guard is authenticated
        elseif (auth()->guard('account')->check()) {
            $account = auth()->guard('account')->user();
            
            // Accounts can never access admin routes
            if ($role === 'admin') {
                abort(403, 'Unauthorized action.');
            }
            
            // Check if account has the required role
            if (!$account->hasRole($role)) {
                abort(403, 'Unauthorized action.');
            }
        }
        else {
            return redirect()->route('login');
        }

        return $next($request);
    }
}