<?php

namespace App\Events;

use App\Models\Account;
use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ApplicationApprovedEvent
{
    use Dispatchable, SerializesModels;

    public Account $account;
    public Application $application;

    public function __construct(Account $account, Application $application)
    {
        $this->account = $account;
        $this->application = $application;

        \Log::info('ApplicationApprovedEvent instantiated', [
            'account_id' => $account->id,
            'account_email' => $account->email,
            'application_id' => $application->id,
        ]);
    }
}
