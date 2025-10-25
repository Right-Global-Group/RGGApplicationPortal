<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Quick command to check schedule configuration
Artisan::command('schedule:check', function () {
    $this->info('Checking scheduled tasks...');
    
    $schedule = app(Schedule::class);
    $events = $schedule->events();
    
    $this->table(
        ['Command', 'Expression', 'Next Run'],
        collect($events)->map(function ($event) {
            return [
                $event->command ?? $event->description,
                $event->expression,
                $event->nextRunDate()->format('Y-m-d H:i:s'),
            ];
        })
    );
    
    $this->info('Total scheduled tasks: ' . count($events));
})->purpose('Display all scheduled tasks');

// Quick test command for your application system
Artisan::command('applications:test-setup', function () {
    $this->info('Testing application system setup...');
    
    // Check if tables exist
    $tables = [
        'applications',
        'application_statuses',
        'application_documents',
        'invoices',
        'gateway_integrations',
        'activity_logs',
    ];
    
    foreach ($tables as $table) {
        if (Schema::hasTable($table)) {
            $this->info("✓ Table '{$table}' exists");
        } else {
            $this->error("✗ Table '{$table}' is missing!");
        }
    }
    
    // Check if commands exist
    $commands = [
        'applications:send-reminders',
        'docusign:sync-statuses',
        'invoices:mark-overdue',
        'gateway:sync-statuses',
        'reports:daily-progress',
    ];
    
    $this->newLine();
    $this->info('Checking commands...');
    
    foreach ($commands as $command) {
        try {
            $exists = Artisan::call($command, ['--help' => true]) !== null;
            $this->info("✓ Command '{$command}' is registered");
        } catch (\Exception $e) {
            $this->error("✗ Command '{$command}' not found!");
        }
    }
    
    $this->newLine();
    $this->info('Setup check complete!');
})->purpose('Test application system setup');