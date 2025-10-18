<?php

namespace App\Observers;

use App\Models\ApplicationStatus;
use App\Jobs\SendApplicationEmail;

class ApplicationStatusObserver
{
    public function updated(ApplicationStatus $status)
    {
        // Auto-trigger actions based on status changes
        match($status->current_step) {
            'application_sent' => $this->handleApplicationSent($status),
            'contract_completed' => $this->handleContractCompleted($status),
            'application_approved' => $this->handleApplicationApproved($status),
            'invoice_paid' => $this->handleInvoicePaid($status),
            default => null,
        };
    }

    private function handleApplicationSent(ApplicationStatus $status)
    {
        // Could trigger additional notifications
    }

    private function handleContractCompleted(ApplicationStatus $status)
    {
        // Auto-submit the contract
        $status->transitionTo('contract_submitted', 'Auto-submitted after signing');
    }

    private function handleApplicationApproved(ApplicationStatus $status)
    {
        // Queue approval email
        SendApplicationEmail::dispatch($status->application, 'approval');
    }

    private function handleInvoicePaid(ApplicationStatus $status)
    {
        // Could auto-create gateway integration record
    }
}
