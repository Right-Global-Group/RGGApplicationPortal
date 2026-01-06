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
     * Get the ordered timeline of completed steps
     * This ensures steps appear in the order they were actually completed
     */
    public function getOrderedTimelineAttribute(): array
    {
        $allSteps = [
            'created' => ['label' => 'Application Created', 'timestamp' => $this->created_at, 'progress' => 0],
            'contract_sent' => ['label' => 'Contract Sent', 'timestamp' => $this->contract_sent_at, 'progress' => 10],
            'documents_uploaded' => ['label' => 'Documents Uploaded', 'timestamp' => $this->documents_uploaded_at, 'progress' => 20],
            'documents_approved' => ['label' => 'Documents Approved', 'timestamp' => $this->documents_approved_at, 'progress' => 30],
            'contract_signed' => ['label' => 'Contract Signed', 'timestamp' => $this->contract_signed_at, 'progress' => 50],
            'contract_submitted' => ['label' => 'Contract Submitted', 'timestamp' => $this->contract_submitted_at, 'progress' => 60],
            'application_approved' => ['label' => 'Application Approved', 'timestamp' => $this->application_approved_at, 'progress' => 70],
            'invoice_sent' => ['label' => 'Invoice Sent', 'timestamp' => $this->invoice_sent_at, 'progress' => 82],
            'invoice_paid' => ['label' => 'Payment Received', 'timestamp' => $this->invoice_paid_at, 'progress' => 90],
            'gateway_integrated' => ['label' => 'Gateway Integration', 'timestamp' => $this->gateway_integrated_at, 'progress' => 95],
            'account_live' => ['label' => 'Account Live', 'timestamp' => $this->account_live_at, 'progress' => 100],
        ];

        // Separate completed and pending steps
        $completed = collect($allSteps)->filter(fn($step) => $step['timestamp'] !== null)
            ->sortBy(fn($step) => $step['timestamp']);
        
        $pending = collect($allSteps)->filter(fn($step) => $step['timestamp'] === null);

        // Reassign progress values based on actual order
        $orderedSteps = [];
        $index = 0;
        $progressIncrement = 100 / count($allSteps);

        // Add completed steps in chronological order
        foreach ($completed as $key => $step) {
            $orderedSteps[$key] = array_merge($step, [
                'progress' => $key === 'account_live' ? 100 : round($index * $progressIncrement),
                'is_completed' => true,
                'is_current' => $key === $this->current_step,
            ]);
            $index++;
        }

        // Add pending steps in their default order
        foreach ($pending as $key => $step) {
            $orderedSteps[$key] = array_merge($step, [
                'progress' => round($index * $progressIncrement),
                'is_completed' => false,
                'is_current' => $key === $this->current_step,
            ]);
            $index++;
        }

        return $orderedSteps;
    }

    /**
     * Get dynamic progress percentage based on completed steps
     */
    public function getProgressPercentageAttribute(): int
    {
        $orderedTimeline = $this->getOrderedTimelineAttribute();
        
        // Find the highest progress value among completed steps
        $maxProgress = 0;
        foreach ($orderedTimeline as $key => $step) {
            if ($step['is_completed']) {
                $maxProgress = max($maxProgress, $step['progress']);
            }
        }

        // Also check current step
        if (isset($orderedTimeline[$this->current_step])) {
            $maxProgress = max($maxProgress, $orderedTimeline[$this->current_step]['progress']);
        }

        return $maxProgress;
    }

    /**
     * Get timestamps for display
     */
    public function getTimestampsAttribute(): array
    {
        return [
            'documents_uploaded' => $this->documents_uploaded_at?->format('Y-m-d H:i'),
            'documents_approved' => $this->documents_approved_at?->format('Y-m-d H:i'),
            'contract_sent' => $this->contract_sent_at?->format('Y-m-d H:i'),
            'contract_signed' => $this->contract_signed_at?->format('Y-m-d H:i'),
            'contract_completed' => $this->contract_completed_at?->format('Y-m-d H:i'),
            'contract_submitted' => $this->contract_submitted_at?->format('Y-m-d H:i'),
            'application_approved' => $this->application_approved_at?->format('Y-m-d H:i'),
            'invoice_sent' => $this->invoice_sent_at?->format('Y-m-d H:i'),
            'invoice_paid' => $this->invoice_paid_at?->format('Y-m-d H:i'),
            'gateway_integrated' => $this->gateway_integrated_at?->format('Y-m-d H:i'),
            'account_live' => $this->account_live_at?->format('Y-m-d H:i'),
        ];
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
    
        // Get the timestamp field for this step
        $timestampField = $this->getTimestampField($newStep);
        
        // Only set timestamp if it doesn't already exist
        // This prevents overwriting existing timestamps
        if ($timestampField && is_null($this->$timestampField)) {
            $updateData[$timestampField] = now();
            
            \Log::info('Setting timestamp for new step', [
                'step' => $newStep,
                'field' => $timestampField,
                'timestamp' => now(),
            ]);
        } elseif ($timestampField) {
            \Log::info('Timestamp already exists, preserving it', [
                'step' => $newStep,
                'field' => $timestampField,
                'existing_timestamp' => $this->$timestampField,
            ]);
        }
    
        // Save the updates
        $this->update($updateData);
        
        // FORCE refresh to ensure timestamps are loaded
        $this->refresh();
    
        // Log the activity
        ActivityLog::create([
            'application_id' => $this->application_id,
            'action' => 'status_changed',
            'description' => "Status changed from {$oldStep} to {$newStep}",
        ]);
    
        // Fire event for real-time updates
        event(new ApplicationStatusChanged($this->application, $oldStep, $newStep));
    }

    /**
     * Manually transition to a specific step without triggering automated actions
     * Used by admin manual override functionality
     */
    public function manualTransitionTo(string $newStep, ?string $notes = null, bool $logActivity = true): void
    {
        $oldStep = $this->current_step;
        $history = $this->step_history ?? [];
        
        $history[] = [
            'from' => $oldStep,
            'to' => $newStep,
            'timestamp' => now()->toISOString(),
            'notes' => $notes,
            'manual_transition' => true, // Mark as manual transition
        ];

        $updateData = [
            'current_step' => $newStep,
            'step_history' => $history,
        ];

        // Get the timestamp field for this step
        $timestampField = $this->getTimestampField($newStep);
        
        // Set timestamp if it doesn't already exist
        if ($timestampField && is_null($this->$timestampField)) {
            $updateData[$timestampField] = now();
        }

        // Save the updates
        $this->update($updateData);
        
        // FORCE refresh to ensure timestamps are loaded
        $this->refresh();

        // Log the activity if requested
        if ($logActivity) {
            ActivityLog::create([
                'application_id' => $this->application_id,
                'action' => 'manual_status_change',
                'description' => "Status manually changed from {$oldStep} to {$newStep}",
            ]);
        }

        // Fire event for real-time updates (but NOT for automated actions)
        // The event system should be modified to check for manual_transition flag
        event(new ApplicationStatusChanged($this->application, $oldStep, $newStep));
    }

    private function getTimestampField(string $step): ?string
    {
        $mapping = [
            'created' => null,
            'contract_sent' => 'contract_sent_at',
            'documents_uploaded' => 'documents_uploaded_at',
            'documents_approved' => 'documents_approved_at',
            'contract_signed' => 'contract_signed_at',
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

    /**
     * FIXED: Check if all required documents are uploaded
     * Now properly checks base documents AND additional documents
     */
    public function hasAllRequiredDocuments(): bool
    {
        $application = $this->application;
        $requiredCategories = array_keys(ApplicationDocument::getRequiredCategories());
        
        // Check base documents
        $uploadedCategories = $application->documents()
            ->whereIn('document_category', $requiredCategories)
            ->pluck('document_category')
            ->unique()
            ->toArray();
    
        \Log::info('Checking required documents', [
            'application_id' => $application->id,
            'required_categories' => $requiredCategories,
            'uploaded_categories' => $uploadedCategories,
            'required_count' => count($requiredCategories),
            'uploaded_count' => count($uploadedCategories),
        ]);
    
        $hasAllBaseDocuments = count($uploadedCategories) === count($requiredCategories);
        
        // Check additional documents - if there are ANY additional document requests,
        // they must ALL be uploaded
        $pendingAdditionalDocs = $application->additionalDocuments()->where('is_uploaded', false)->get();
        $hasAllAdditionalDocuments = $pendingAdditionalDocs->isEmpty();
        
        \Log::info('Document check results', [
            'has_all_base_documents' => $hasAllBaseDocuments,
            'has_all_additional_documents' => $hasAllAdditionalDocuments,
            'pending_additional_count' => $pendingAdditionalDocs->count(),
            'final_result' => $hasAllBaseDocuments && $hasAllAdditionalDocuments,
        ]);
        
        return $hasAllBaseDocuments && $hasAllAdditionalDocuments;
    }

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

    public function hasContractBeenViewed(): bool
    {
        return !is_null($this->contract_viewed_at);
    }

    public function isContractFullySigned(): bool
    {
        return !is_null($this->contract_signed_at) && $this->hasAllRecipientsSigned();
    }

    public function getDaysSinceContractSent(): ?int
    {
        if (!$this->contract_sent_at) {
            return null;
        }

        return now()->diffInDays($this->contract_sent_at);
    }

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

    public function getRecipientByEmail(string $email): ?array
    {
        if (!$this->docusign_recipient_status) {
            return null;
        }

        return collect($this->docusign_recipient_status)
            ->firstWhere('email', $email);
    }

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

    public function canProceedToNextStep(): bool
    {
        $currentStep = $this->current_step;

        switch ($currentStep) {
            case 'created':
                return true;

            case 'contract_sent':
                return true;

            case 'documents_uploaded':
                return false; // Manual approval required

            case 'documents_approved':
                return true;

            case 'contract_signed':
                return true;

            case 'contract_submitted':
                return false; // Needs manual approval

            default:
                return false;
        }
    }

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