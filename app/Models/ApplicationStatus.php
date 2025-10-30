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

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function getProgressPercentageAttribute(): int
    {
        $steps = [
            'created' => 0,
            'documents_uploaded' => 15,
            'documents_approved' => 20,
            'application_sent' => 25,
            'contract_completed' => 40,
            'contract_submitted' => 50,
            'application_approved' => 65,
            'approval_email_sent' => 70,
            'gateway_contract_sent' => 72,
            'gateway_contract_signed' => 74,
            'gateway_details_received' => 76,
            'wordpress_credentials_collected' => 78,
            'invoice_sent' => 80,
            'invoice_paid' => 88,
            'gateway_integrated' => 92,
            'account_live' => 100,
        ];

        return $steps[$this->current_step] ?? 0;
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

    private function getTimestampField(string $step): ?string
    {
        $mapping = [
            'documents_uploaded' => 'documents_uploaded_at',
            'documents_approved' => 'documents_approved_at',
            'application_sent' => 'contract_sent_at',
            'contract_completed' => 'contract_completed_at',
            'contract_submitted' => 'contract_submitted_at',
            'gateway_contract_sent' => 'gateway_contract_sent_at',
            'gateway_contract_signed' => 'gateway_contract_signed_at',
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
}