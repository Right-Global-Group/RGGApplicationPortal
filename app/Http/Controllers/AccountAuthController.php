<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountAuthController extends Controller
{
    public function showLoginForm(): Response
    {
        return Inertia::render('Auth/AccountLogin');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Ensure no web guard session exists (prevent guard conflicts)
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        if (Auth::guard('account')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $account = Auth::guard('account')->user();
            
            // Mark account as confirmed on first login
            if (!$account->isConfirmed()) {
                $account->markAsConfirmed();
            }

            return redirect()->intended(route('accounts.edit', $account));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('account')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('account.login');
    }
}