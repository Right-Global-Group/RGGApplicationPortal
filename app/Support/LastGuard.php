<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Remembers which login page a visitor belongs to.
 *
 * Protected routes accept both the `web` and `account` guards, so when a session lapses
 * there is nothing on the request to say whether the guest is staff or a merchant. Sending
 * a merchant to the staff form is a dead end they cannot escape - the bug this feature
 * exists to kill - so we record the guard at login and read it back when they return.
 *
 * This only chooses which form to show. It grants nothing, so a stale or forged cookie
 * costs a visitor a wrong login page and no more.
 */
class LastGuard
{
    public const COOKIE = 'portal_guard';

    public static function remember(string $guard): void
    {
        Cookie::queue(Cookie::forever(self::COOKIE, $guard));
    }

    /**
     * The login route a guest should be sent to. Staff is the default: merchants only
     * ever arrive here having clicked a link, which sets the cookie on the way through.
     */
    public static function loginRouteFor(Request $request): string
    {
        return $request->cookie(self::COOKIE) === 'account'
            ? route('account.login')
            : route('login');
    }
}
