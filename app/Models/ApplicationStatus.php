<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Events\ApplicationStatusChanged;

class ApplicationStatus extends Model
{
    protected $fillable = [
        'application_id',
        'current_step',
        'step_history',
        'documents_uploaded_at',
        'documents_approved_at',
        'contract_sent_at',
        'docusign_recipient_status',
        'contract_viewed_at',
        'contract_signed_at',
        'contract_completed_at',
        'contract_submitted_at',
        'gateway_contract_sent_at',
        'gateway_contract_signed_at',
        'wordpress_credentials_collected_at',
        'application_approved_at',
        'invoice_sent_at',
        'invoice_paid_at',
        'gateway_integrated_at',
        'account_live_at',
        'docusign_envelope_id',
        'docusign_status',
        'gateway_docusign_envelope_id',
        'requires_additional_info',
        'additional_info_notes',
    ];

    protected $casts = [
        'step_history' => 'array',
        'documents_uploaded_at' => 'datetime',
        'documents_approved_at' => 'datetime',
        'contract_sent_at' => 'datetime',
        'contract_completed_at' => 'datetime',
        'contract_submitted_at' => 'datetime',
        'docusign_recipient_status' => 'array',
        'contract_viewed_at' => 'datetime',
        'contract_signed_at' => 'datetime',
        'gateway_contract_sent_at' => 'datetime',
        'gateway_contract_signed_at' => 'datetime',
        'wordpress_credentials_collected_at' => 'datetime',
        'application_approved_at' => 'datetime',
        'invoice_sent_at' => 'datetime',
        'invoice_paid_at' => 'datetime',
        'gateway_integrated_at' => 'datetime',
        'account_live_at' => 'datetime',
        'requires_additional_info' => 'boolean',
    ];

    /**
     * UPDATE THE EXISTING getProgressPercentageAttribute METHOD
     * to include new steps in correct order
     */
    public function getProgressPercentageAttribute(): int
    {
        $steps = [
            'created' => 0,
            'contract_sent' => 10,
            'documents_uploaded' => 20,
            'documents_approved' => 30,
            'contract_signed' => 50,
            'contract_submitted' => 60,
            'application_approved' => 70,
            'approval_email_sent' => 72,
            'gateway_contract_sent' => 74,
            'gateway_contract_signed' => 76,
            'gateway_details_received' => 78,
            'wordpress_credentials_collected' => 80,
            'invoice_sent' => 82,
            'invoice_paid' => 90,
            'gateway_integrated' => 95,
            'account_live' => 100,
        ];
    
        // Get current step's progress
        $currentProgress = $steps[$this->current_step] ?? 0;
        
        // ✅ NEW: Calculate maximum progress based on completed timestamps
        $maxCompletedProgress = 0;
        
        $timestampMapping = [
            'contract_sent_at' => 10,
            'documents_uploaded_at' => 20,
            'documents_approved_at' => 30,
            'contract_signed_at' => 50,
            'contract_submitted_at' => 60,
            'application_approved_at' => 70,
            'gateway_contract_sent_at' => 74,
            'gateway_contract_signed_at' => 76,
            'wordpress_credentials_collected_at' => 80,
            'invoice_sent_at' => 82,
            'invoice_paid_at' => 90,
            'gateway_integrated_at' => 95,
            'account_live_at' => 100,
        ];
        
        // Check all completed timestamps and get highest progress
        foreach ($timestampMapping as $field => $progress) {
            if (!is_null($this->$field)) {
                $maxCompletedProgress = max($maxCompletedProgress, $progress);
            }
        }
        
        // Return the HIGHER of current step or max completed
        return max($currentProgress, $maxCompletedProgress);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function transitionTo(string $newStep, ?string $notes = null): void
    {
        $oldStep = $this->current_step;
        $history = $this->step_history ?? [];
        
        $history[] = [
            'from' => $oldStep,
            'to' => $newStep,
            'timestamp' => now()->toISOString(),
            'notes' => $notes,
        ];

        $updateData = [
            'current_step' => $newStep,
            'step_history' => $history,
        ];

        $timestampField = $this->getTimestampField($newStep);
        if ($timestampField) {
            $updateData[$timestampField] = now();
        }

        $this->update($updateData);

        // Log the activity
        ActivityLog::create([
            'application_id' => $this->application_id,
            'action' => 'status_changed',
            'description' => "Status changed from {$oldStep} to {$newStep}",
            'metadata' => ['notes' => $notes],
        ]);

        // Fire event for real-time updates
        event(new ApplicationStatusChanged($this->application, $oldStep, $newStep));
    }

    /**
     * UPDATE THE EXISTING getTimestampField METHOD
     * to include new timestamp fields
     */
    private function getTimestampField(string $step): ?string
    {
        $mapping = [
            'created' => null,
            'contract_sent' => 'contract_sent_at',
            'documents_uploaded' => 'documents_uploaded_at',
            'documents_approved' => 'documents_approved_at',
            'contract_signed' => 'contract_signed_at',      // NEW
            'contract_submitted' => 'contract_submitted_at',
            'gateway_contract_sent' => 'gateway_contract_sent_at',
            'gateway_contract_signed' => 'gateway_contract_signed_at',
            'gateway_details_received' => null,
            'wordpress_credentials_collected' => 'wordpress_credentials_collected_at',
            'application_approved' => 'application_approved_at',
            'invoice_sent' => 'invoice_sent_at',
            'invoice_paid' => 'invoice_paid_at',
            'gateway_integrated' => 'gateway_integrated_at',
            'account_live' => 'account_live_at',
        ];

        return $mapping[$step] ?? null;
    }

    public function hasAllRequiredDocuments(): bool
    {
        $application = $this->application;
        $requiredCategories = array_keys(ApplicationDocument::getRequiredCategories());
        
        $uploadedCategories = $application->documents()
            ->whereIn('document_category', $requiredCategories)
            ->pluck('document_category')
            ->unique()
            ->toArray();
    
        // Check base documents
        $hasAllBaseDocuments = count($uploadedCategories) === count($requiredCategories);
        
        // Check all additional documents are uploaded
        $hasAllAdditionalDocuments = $application->hasAllAdditionalDocumentsUploaded();
        
        return $hasAllBaseDocuments && $hasAllAdditionalDocuments;
    }

    /**
     * NEW METHOD: Check if all required signers have signed the contract
     */
    public function hasAllRecipientsSigned(): bool
    {
        if (!$this->docusign_recipient_status) {
            return false;
        }

        $signers = collect($this->docusign_recipient_status)
            ->where('type', 'signers');

        if ($signers->isEmpty()) {
            return false;
        }

        return $signers->every(function ($signer) {
            return in_array($signer['status'] ?? '', ['completed', 'signed']);
        });
    }

    /**
     * NEW METHOD: Get formatted recipient status for display
     */
    public function getFormattedRecipientStatus(): array
    {
        if (!$this->docusign_recipient_status) {
            return [];
        }

        return collect($this->docusign_recipient_status)->map(function ($recipient) {
            return [
                'name' => $recipient['name'] ?? 'Unknown',
                'email' => $recipient['email'] ?? '',
                'status' => $recipient['status'] ?? 'pending',
                'signed_at' => $recipient['signed_at'] ?? null,
                'delivered_at' => $recipient['delivered_at'] ?? null,
                'type' => $recipient['type'] ?? 'signer',
            ];
        })->toArray();
    }

    /**
     * NEW METHOD: Check if contract has been viewed
     */
    public function hasContractBeenViewed(): bool
    {
        return !is_null($this->contract_viewed_at);
    }

    /**
     * NEW METHOD: Check if contract is fully signed
     */
    public function isContractFullySigned(): bool
    {
        return !is_null($this->contract_signed_at) && $this->hasAllRecipientsSigned();
    }

    /**
     * NEW METHOD: Get days since contract was sent
     */
    public function getDaysSinceContractSent(): ?int
    {
        if (!$this->contract_sent_at) {
            return null;
        }

        return now()->diffInDays($this->contract_sent_at);
    }

    /**
     * NEW METHOD: Check if contract reminder should be sent
     * (Contract sent but not viewed after 3 days)
     */
    public function shouldSendContractReminder(): bool
    {
        if ($this->current_step !== 'contract_sent') {
            return false;
        }

        if ($this->hasContractBeenViewed()) {
            return false;
        }

        $daysSince = $this->getDaysSinceContractSent();
        
        return $daysSince !== null && $daysSince >= 3;
    }

    /**
     * NEW METHOD: Get recipient by email
     */
    public function getRecipientByEmail(string $email): ?array
    {
        if (!$this->docusign_recipient_status) {
            return null;
        }

        return collect($this->docusign_recipient_status)
            ->firstWhere('email', $email);
    }

    /**
     * NEW METHOD: Update recipient status
     */
    public function updateRecipientStatus(string $email, array $updates): void
    {
        if (!$this->docusign_recipient_status) {
            $this->docusign_recipient_status = [];
        }

        $recipients = collect($this->docusign_recipient_status);
        
        $index = $recipients->search(function ($recipient) use ($email) {
            return $recipient['email'] === $email;
        });

        if ($index !== false) {
            $recipient = $recipients[$index];
            $recipients[$index] = array_merge($recipient, $updates);
        } else {
            $recipients->push(array_merge(['email' => $email], $updates));
        }

        $this->docusign_recipient_status = $recipients->toArray();
        $this->save();
    }

    /**
     * UPDATED METHOD: Check if can proceed to next step
     * Now includes contract signing requirements
     */
    public function canProceedToNextStep(): bool
    {
        $currentStep = $this->current_step;

        switch ($currentStep) {
            case 'created':
                // Can proceed after contract is sent
                return true;

            case 'contract_sent':
                // Can proceed to documents_uploaded anytime
                // Contract signing happens in parallel
                return true;

            case 'documents_uploaded':
                // Need documents approved
                return false; // Manual approval required

            case 'documents_approved':
                // Can proceed once approved
                return true;

            case 'contract_signed':
                // Can proceed to submission
                return true;

            case 'contract_submitted':
                // Needs manual approval
                return false;

            default:
                return false;
        }
    }

    /**
     * NEW METHOD: Get next recommended action based on current step
     */
    public function getNextRecommendedAction(): ?string
    {
        switch ($this->current_step) {
            case 'created':
                return 'Send contract to merchant for signature';

            case 'contract_sent':
                if (!$this->hasContractBeenViewed()) {
                    return 'Waiting for merchant to view contract';
                }
                if (!$this->isContractFullySigned()) {
                    return 'Waiting for all parties to sign contract';
                }
                return 'Upload required documents';

            case 'documents_uploaded':
                return 'Review and approve documents';

            case 'documents_approved':
                if (!$this->isContractFullySigned()) {
                    return 'Waiting for contract to be fully signed';
                }
                return 'Contract ready for submission';

            case 'contract_signed':
                return 'Submit contract to CardStream';

            case 'contract_submitted':
                return 'Review and approve application';

            case 'application_approved':
                return 'Create and send invoice';

            default:
                return null;
        }
    }
}