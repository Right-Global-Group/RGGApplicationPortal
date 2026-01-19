<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
    
        Log::info('Account login attempt', [
            'email' => $validated['email'],
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    
        // Ensure no web guard session exists (prevent guard conflicts)
        if (Auth::guard('web')->check()) {
            Log::warning('Web guard session detected during account login, logging out web guard', [
                'email' => $validated['email'],
                'web_user_id' => Auth::guard('web')->id(),
            ]);
            Auth::guard('web')->logout();
        }
    
        $credentials = [
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
        ];
    
        Log::info('Attempting authentication with account guard', [
            'email' => $credentials['email'],
            'normalized_email' => $credentials['email'],
            'original_email' => $validated['email'],
        ]);
    
        if (Auth::guard('account')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
    
            // Set a session flag to clear the status page refresh indicator
            $request->session()->put('just_logged_in', true);
    
            $account = Auth::guard('account')->user();
            
            Log::info('Account login successful', [
                'account_id' => $account->id,
                'email' => $account->email,
                'is_confirmed' => $account->isConfirmed(),
                'session_id' => $request->session()->getId(),
            ]);
    
            // Mark account as confirmed on first login
            if (!$account->isConfirmed()) {
                Log::info('Marking account as confirmed on first login', [
                    'account_id' => $account->id,
                ]);
                $account->markAsConfirmed();
            }
    
            // Redirect to intended URL (from email link) or default to account edit page
            $intendedUrl = redirect()->intended(route('accounts.edit', $account))->getTargetUrl();
            Log::info('Redirecting after successful login', [
                'account_id' => $account->id,
                'intended_url' => $intendedUrl,
            ]);
    
            return redirect()->intended(route('accounts.edit', $account));
        }
    
        Log::warning('Account login failed - credentials mismatch', [
            'email' => $credentials['email'],
            'ip' => $request->ip(),
            'session_id' => $request->session()->getId(),
        ]);
    
        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        $account = Auth::guard('account')->user();
        
        Log::info('Account logout', [
            'account_id' => $account?->id,
            'email' => $account?->email,
        ]);

        Auth::guard('account')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('account.login');
    }
}