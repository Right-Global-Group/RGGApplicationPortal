<?php

namespace App\Events;

use App\Models\Account;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AccountCredentialsEvent
{
    use Dispatchable, SerializesModels;

    public Account $account;
    public string $plainPassword;

    public function __construct(Account $account, string $plainPassword)
    {
        $this->account = $account;
        $this->plainPassword = $plainPassword;

        \Log::info('AccountCredentialsEvent instantiated', [
            'account_id' => $account->id,
            'account_email' => $account->email,
        ]);
    }
}