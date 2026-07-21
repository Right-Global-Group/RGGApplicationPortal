<?php

namespace App\Http\Controllers;

use App\Events\AccountCredentialsEvent;
use App\Models\Account;
use App\Models\Application;
use App\Support\LastGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

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
    public function login(Request $request, Account $account): RedirectResponse|Response
    {
        if (! URL::hasCorrectSignature($request)) {
            Log::warning('Magic link rejected - bad signature', [
                'account_id' => $account->id,
                'ip' => $request->ip(),
            ]);

            abort(403, 'This login link is not valid.');
        }

        // Expiry is not a failure the merchant should have to understand: send a fresh
        // link and tell them to look. The address comes from the signed URL, never from
        // input, so this cannot be aimed at a stranger.
        if (! URL::signatureHasNotExpired($request)) {
            $this->sendFreshLink($request, $account);

            return Inertia::render('Auth/LinkSent', [
                'email' => $account->email,
            ]);
        }

        // One browser, one identity: controllers scope on `account` before `web`, while
        // `role:admin` reads `web`, so two live guards is merchant data on some pages and
        // staff pages on others. Only reached once the signature is good.
        Auth::guard('web')->logout();

        Auth::guard('account')->login($account, remember: true);

        $request->session()->regenerate();
        $request->session()->put('just_logged_in', true);
        LastGuard::remember('account');

        if (! $account->first_login_at) {
            $account->update(['first_login_at' => now()]);
        }

        Log::info('Magic link login', ['account_id' => $account->id]);

        return redirect()->intended($this->landingUrl($request));
    }

    /**
     * The "send me a link" box, for a merchant who no longer has the email.
     */
    public function showRequestForm(): Response
    {
        return Inertia::render('Auth/RequestLink');
    }

    /**
     * The reply is the same whether or not the address is one of ours: this box is
     * public, so a difference between the two would publish G2Pay's merchant list.
     */
    public function sendRequestedLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $account = Account::where('email', strtolower($validated['email']))->first();

        if ($account) {
            event(AccountCredentialsEvent::for($account));
        }

        return back()->with('success', "If that address is registered, we've sent it a login link.");
    }

    /**
     * Re-issue the link the merchant just tried to use, aimed at the same place it was.
     */
    private function sendFreshLink(Request $request, Account $account): void
    {
        // A link is a bearer token that may be sitting in more than one inbox; without a
        // ceiling, repeated clicks on a stale one are a mail bomb aimed at the merchant.
        $key = 'magic-link-reissue:'.$account->id;

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            Log::warning('Magic link re-issue rate limited', ['account_id' => $account->id]);

            return;
        }

        RateLimiter::hit($key, decaySeconds: 3600);

        $application = ($id = $request->query('application'))
            ? Application::find($id)
            : null;

        $redirect = $request->query('redirect');

        event(new AccountCredentialsEvent(
            $account,
            $application,
            is_string($redirect) ? $redirect : null,
        ));

        Log::info('Magic link expired - fresh link sent', ['account_id' => $account->id]);
    }

    /**
     * Where the link says to land. Both parameters are covered by the signature, so
     * they cannot be pointed somewhere else by editing the URL; the relative-path
     * check keeps a signing mistake from becoming an open redirect.
     */
    private function landingUrl(Request $request): string
    {
        $redirect = $request->query('redirect');

        if (is_string($redirect) && str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//')) {
            return url($redirect);
        }

        if ($applicationId = $request->query('application')) {
            return route('applications.status', $applicationId);
        }

        // The "send me a link" box carries no task, so this fallback is reachable by
        // design. Not accounts.edit: that is the staff CRM screen, scoped but not for them.
        return route('applications');
    }
}
