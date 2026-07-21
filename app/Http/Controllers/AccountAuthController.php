<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Merchants sign in by clicking a link (see MagicLinkController), so there is no
 * credential form here to log out of the web guard "to avoid a conflict" - that block
 * ran before the credential check and never re-authenticated, so a staff member who
 * mistyped on the merchant form was silently logged out of their own admin session.
 */
class AccountAuthController extends Controller
{
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
