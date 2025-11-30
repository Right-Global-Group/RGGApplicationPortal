<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Application;
use App\Models\User;
use App\Services\DocuSignService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DocuSignGoLiveTest extends Command
{
    protected $signature = 'docusign:test-golive 
                            {--applications=10 : Number of test applications to create}
                            {--delay=15 : Minutes to wait between each application}';

    protected $description = 'Generate 20+ API calls for DocuSign Go-Live review (minimal version)';

    private DocuSignService $docuSignService;
    private int $totalApiCalls = 0;

    public function __construct(DocuSignService $docuSignService)
    {
        parent::__construct();
        $this->docuSignService = $docuSignService;
    }

    public function handle(): int
    {
        $this->info('🚀 DocuSign Go-Live Test - Simplified Version');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        $numApplications = (int) $this->option('applications');
        $delayMinutes = (int) $this->option('delay');
        
        $this->warn('⚠️  This will take approximately ' . ($numApplications * $delayMinutes) . ' minutes');
        $this->warn('⚠️  Do NOT stop this script until it completes');
        $this->newLine();
        
        if (!$this->confirm('Start test?', true)) {
            return Command::SUCCESS;
        }

        // Get or create single test user and account (reused for all applications)
        $testUser = $this->getOrCreateTestUser();
        $testAccount = $this->getOrCreateTestAccount($testUser);
        
        $this->info("Using test user: {$testUser->email}");
        $this->info("Using test account: {$testAccount->email}");
        $this->newLine();

        // Track start time
        $startTime = now();

        for ($i = 1; $i <= $numApplications; $i++) {
            $iterationStart = now();
            
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📝 Application {$i}/{$numApplications} | Time: " . $iterationStart->format('H:i:s'));
            
            try {
                // Step 1: Create test application
                $application = $this->createTestApplication($testAccount, $testUser, $i);
                $this->line("   ✓ Created: {$application->name}");
                
                // Step 2: Send contract to DocuSign
                // 1. POST /envelopes (create envelope)
                // 2. POST /envelopes/{id}/views/recipient (get embedded signing URL)
                $this->line("   🔹 Calling sendDocuSignContract()...");
                $result = $this->docuSignService->sendDocuSignContract($application);
                
                $this->totalApiCalls += 2; // Envelope creation + recipient view
                $this->line("   ✓ Envelope created: {$result['envelope_id']}");
                $this->line("   ✓ API calls: +2 (create envelope, get signing URL)");
                
                // Step 3: Check envelope status
                // Optional but adds variety to API calls
                sleep(3); // Brief pause to avoid rate limiting
                $this->line("   🔹 Checking envelope status...");
                $recipients = $this->docuSignService->getEnvelopeRecipients($result['envelope_id']);
                
                $this->totalApiCalls += 1; // Get recipients
                $this->line("   ✓ Retrieved recipient status");
                $this->line("   ✓ API calls: +1 (get recipients)");
                
                $this->info("   ✅ Complete | Total API calls so far: {$this->totalApiCalls}");
                
                // Wait before next iteration (except on last one)
                if ($i < $numApplications) {
                    $this->newLine();
                    $this->waitWithProgress($delayMinutes);
                }
                
            } catch (\Exception $e) {
                $this->error("   ❌ Error: {$e->getMessage()}");
                Log::error('DocuSign Go-Live test failed', [
                    'application_number' => $i,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                // Continue anyway after delay
                if ($i < $numApplications) {
                    $this->warn("   Continuing to next application...");
                    $this->waitWithProgress($delayMinutes);
                }
            }
        }

        // Final summary
        $this->displaySummary($startTime);
        
        return Command::SUCCESS;
    }

    private function createTestApplication(Account $account, User $user, int $index): Application
    {
        return Application::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'name' => "GoLive Test #{$index}",
            'trading_name' => "Test Merchant {$index}",
            'company_type' => 'limited_company',
            'business_email' => "test{$index}@example.com",
            'business_phone' => '07700900000',
            'website_url' => "https://test{$index}.example.com",
            'business_description' => "Test for DocuSign API review",
            'monthly_turnover' => 10000,
            'average_transaction_value' => 50,
            'card_present_percentage' => 0,
            'card_not_present_percentage' => 100,
            'business_established_date' => now()->subYears(2),
            
            // Fees
            'transaction_percentage' => 1.5,
            'transaction_fixed_fee' => 0.20,
            'monthly_fee' => 25.00,
            'monthly_minimum' => 50.00,
            'scaling_fee' => 495.00,
            'setup_fee' => 0.00,
            
            'status' => 'pending',
        ]);
    }

    private function getOrCreateTestUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'docusign.golive@g2pay.co.uk'],
            [
                'name' => 'DocuSign Go-Live Tester',
                'password' => bcrypt('test-' . uniqid()),
                'role' => 'admin',
            ]
        );
    }

    private function getOrCreateTestAccount(User $user): Account
    {
        return Account::firstOrCreate(
            ['email' => 'docusign.golive.merchant@example.com'],
            [
                'name' => 'Test Merchant Ltd',
                'user_id' => $user->id,
                'mobile' => '07700900000',
                'email_verified_at' => now(),
                'password' => bcrypt('test-' . uniqid()),
            ]
        );
    }

    private function waitWithProgress(int $minutes): void
    {
        $seconds = $minutes * 60;
        $this->info("⏳ Waiting {$minutes} minutes...");
        
        $bar = $this->output->createProgressBar($seconds);
        $bar->setFormat('   [%bar%] %elapsed:6s% / %estimated:-6s%');
        $bar->start();
        
        for ($i = 0; $i < $seconds; $i++) {
            sleep(1);
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
    }

    private function displaySummary($startTime): void
    {
        $duration = $startTime->diffInMinutes(now());
        
        $this->newLine(2);
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🎉 TEST COMPLETE');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();
        
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total API Calls', $this->totalApiCalls],
                ['Duration', "{$duration} minutes"],
                ['Status', $this->totalApiCalls >= 20 ? '✅ READY' : '⚠️  NEED MORE'],
            ]
        );
        
        $this->newLine();
        
        if ($this->totalApiCalls >= 20) {
            $this->info('✅ SUCCESS! You have 20+ API calls.');
            $this->newLine();
            $this->info('📋 Next Steps:');
            $this->line('   1. Go to DocuSign Admin → API and Keys');
            $this->line('   2. Click on your Integration Key');
            $this->line('   3. Check "Actions & Usage" tab');
            $this->line('   4. Verify last 20 calls show SUCCESS');
            $this->line('   5. Click "Go Live" button');
            $this->line('   6. Enter Production Account ID: 593197466');
        } else {
            $this->warn('⚠️  You need ' . (20 - $this->totalApiCalls) . ' more API calls');
            $this->line('   Run: php artisan docusign:test-golive --applications=' . ceil((20 - $this->totalApiCalls) / 3));
        }
        
        $this->newLine();
        $this->comment('💡 Tip: Wait 1-2 hours before submitting Go-Live request');
        $this->comment('   This ensures all logs are properly recorded in DocuSign');
    }
}