<?php

namespace App\Events;

use App\Models\Account;
use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountCredentialsEvent
{
    use Dispatchable, SerializesModels;

    /**
     * Carries no credential. The link is minted by the listener at send time, so there is
     * never a secret on the row for an unrelated action to invalidate.
     */
    public function __construct(
        public Account $account,
        public ?Application $application = null,
        public ?string $redirectTo = null,
    ) {}
}
