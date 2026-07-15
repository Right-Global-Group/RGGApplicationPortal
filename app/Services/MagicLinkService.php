<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Application;
use Illuminate\Support\Facades\URL;

class MagicLinkService
{
    /**
     * The window is this short only because clicking an expired link mails a fresh one.
     * Lengthening it means revisiting that self-heal first.
     */
    public const LIFETIME_DAYS = 7;

    /**
     * Build a login link for an account.
     *
     * Nothing is stored: the account, the landing target and the expiry all live in the
     * signature, so no later action can invalidate a link we have already sent.
     *
     * @param  string|null  $redirectTo  Relative path to land on, overriding $application.
     */
    public static function for(Account $account, ?Application $application = null, ?string $redirectTo = null): string
    {
        return URL::temporarySignedRoute(
            'account.magic-link',
            now()->addDays(self::LIFETIME_DAYS),
            array_filter([
                'account' => $account->id,
                'application' => $application?->id,
                'redirect' => $redirectTo,
            ]),
        );
    }
}
