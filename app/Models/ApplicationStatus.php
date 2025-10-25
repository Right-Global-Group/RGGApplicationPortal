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
        'fees_confirmed_at',
        'documents_uploaded_at',
        'contract_sent_at',
        'contract_completed_at',
        'contract_submitted_at',
        'application_approved_at',
        'invoice_sent_at',
        'invoice_paid_at',
        'gateway_integrated_at',
        'account_live_at',
        'docusign_envelope_id',
        'docusign_status',
        'requires_additional_info',
        'additional_info_notes',
    ];

    protected $casts = [
        'step_history' => 'array',
        'fees_confirmed_at' => 'datetime',
        'documents_uploaded_at' => 'datetime',
        'contract_sent_at' => 'datetime',
        'contract_completed_at' => 'datetime',
        'contract_submitted_at' => 'datetime',
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
            'fees_confirmed' => 5,
            'documents_uploaded' => 15,
            'application_sent' => 25,
            'contract_completed' => 40,
            'contract_submitted' => 50,
            'application_approved' => 65,
            'approval_email_sent' => 70,
            'invoice_sent' => 75,
            'invoice_paid' => 85,
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

        $this->update([
            'current_step' => $newStep,
            'step_history' => $history,
            $this->getTimestampField($newStep) => now(),
        ]);

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
            'fees_confirmed' => 'fees_confirmed_at',
            'documents_uploaded' => 'documents_uploaded_at',
            'application_sent' => 'contract_sent_at',
            'contract_completed' => 'contract_completed_at',
            'contract_submitted' => 'contract_submitted_at',
            'application_approved' => 'application_approved_at',
            'invoice_sent' => 'invoice_sent_at',
            'invoice_paid' => 'invoice_paid_at',
            'gateway_integrated' => 'gateway_integrated_at',
            'account_live' => 'account_live_at',
        ];

        return $mapping[$step] ?? null;
    }

    /**
     * Check if all required documents have been uploaded
     */
    public function hasAllRequiredDocuments(): bool
    {
        $requiredCategories = array_keys(ApplicationDocument::getRequiredCategories());
        $uploadedCategories = $this->application->documents()
            ->whereIn('document_category', $requiredCategories)
            ->pluck('document_category')
            ->unique()
            ->toArray();

        return count($uploadedCategories) === count($requiredCategories);
    }
}