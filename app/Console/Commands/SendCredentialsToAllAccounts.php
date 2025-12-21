<?php

namespace App\Console\Commands;

use App\Events\AccountCredentialsEvent;
use App\Models\Account;
use Illuminate\Console\Command;

class SendCredentialsToAllAccounts extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'accounts:send-credentials 
                            {--filter= : Filter accounts (all|pending|never-sent)}
                            {--dry-run : Preview what would be sent without actually sending}';

    /**
     * The console command description.
     */
    protected $description = 'Send login credentials to accounts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filter = $this->option('filter') ?? 'pending';
        $dryRun = $this->option('dry-run');

        $this->info("🔍 Fetching accounts (filter: {$filter})...");

        // Build query based on filter
        $query = Account::query();

        switch ($filter) {
            case 'all':
                // Send to all accounts
                break;
            case 'never-sent':
                // Only accounts that have never received credentials
                $query->whereNull('credentials_sent_at');
                break;
            case 'pending':
            default:
                // Only accounts that haven't logged in yet
                $query->whereNull('first_login_at');
                break;
        }

        $accounts = $query->get();

        if ($accounts->isEmpty()) {
            $this->warn('❌ No accounts found matching the filter.');
            return Command::SUCCESS;
        }

        $this->info("📧 Found {$accounts->count()} account(s)");

        if ($dryRun) {
            $this->warn('🏃 DRY RUN MODE - No emails will be sent');
            $this->table(
                ['ID', 'Name', 'Email', 'Credentials Sent', 'First Login'],
                $accounts->map(fn($account) => [
                    $account->id,
                    $account->name,
                    $account->email,
                    $account->credentials_sent_at?->format('Y-m-d H:i') ?? 'Never',
                    $account->first_login_at?->format('Y-m-d H:i') ?? 'Not logged in',
                ])
            );
            return Command::SUCCESS;
        }

        // Confirm before sending
        if (!$this->confirm("Send credentials to {$accounts->count()} account(s)?", false)) {
            $this->info('❌ Cancelled.');
            return Command::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($accounts->count());
        $progressBar->start();

        $sent = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            try {
                // Generate new password
                $plainPassword = Account::generatePassword();
                $account->update(['password' => $plainPassword]);

                // Fire event to send email
                event(new AccountCredentialsEvent($account, $plainPassword));

                $sent++;
                $this->newLine();
                $this->info("✅ Sent to: {$account->name} ({$account->email})");
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("❌ Failed for {$account->name}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info("📊 Summary:");
        $this->info("  ✅ Successfully sent: {$sent}");
        if ($failed > 0) {
            $this->error("  ❌ Failed: {$failed}");
        }

        return Command::SUCCESS;
    }
}