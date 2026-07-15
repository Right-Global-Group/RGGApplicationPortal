<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class MagicLinkController extends Controller
{
    /**
     * Log a merchant in from a link in their inbox and drop them on the job.
     *
     * The `signed` middleware is deliberately not used: it aborts 403 on expiry, and
     * expiry is not a dead end here. Correctness and freshness are checked separately
     * because hasValidSignature() collapses "tampered" and "expired" into one false,
     * and those two cases must end differently.
     */
    public function login(Request $request, Account $account): RedirectResponse
    {
        if (! URL::hasCorrectSignature($request)) {
            Log::warning('Magic link rejected - bad signature', [
                'account_id' => $account->id,
                'ip' => $request->ip(),
            ]);

            abort(403, 'This login link is not valid.');
        }

        if (! URL::signatureHasNotExpired($request)) {
            abort(403, 'This login link has expired.');
        }

        Auth::guard('account')->login($account, remember: true);

        $request->session()->regenerate();
        $request->session()->put('just_logged_in', true);

        if (! $account->first_login_at) {
            $account->update(['first_login_at' => now()]);
        }

        Log::info('Magic link login', ['account_id' => $account->id]);

        return redirect()->intended($this->landingUrl($request, $account));
    }

    /**
     * Where the link says to land. Both parameters are covered by the signature, so
     * they cannot be pointed somewhere else by editing the URL; the relative-path
     * check keeps a signing mistake from becoming an open redirect.
     */
    private function landingUrl(Request $request, Account $account): string
    {
        $redirect = $request->query('redirect');

        if (is_string($redirect) && str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            return url($redirect);
        }

        if ($applicationId = $request->query('application')) {
            return route('applications.status', $applicationId);
        }

        return route('accounts.edit', $account);
    }
}
