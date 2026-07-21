<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationMessage extends Model
{
    protected $fillable = [
        'application_id',
        'user_id',
        'account_id',
        'body',
        'is_internal',
        'current_step',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    protected $attributes = [
        'is_internal' => false,
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        // withTrashed: a soft-deleted staff member keeps their name on old messages
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }

    /**
     * Messages the merchant (account guard) is allowed to see.
     */
    #[Scope]
    protected function visibleToAccount(Builder $query): Builder
    {
        return $query->where('is_internal', false);
    }

    /**
     * Merchant messages always carry an account_id, so its absence marks a staff
     * author — even after the staff User row is deleted (user_id nulls on delete).
     */
    public function isFromStaff(): bool
    {
        return is_null($this->account_id);
    }

    /**
     * Who wrote the message — display name plus actor flag — so the UI never
     * cares which model authored it.
     */
    protected function author(): Attribute
    {
        return Attribute::get(function (): array {
            if ($this->isFromStaff()) {
                $name = $this->user
                    ? trim("{$this->user->first_name} {$this->user->last_name}")
                    : 'Staff';
            } else {
                $name = $this->account?->name ?? 'Merchant';
            }

            return [
                'name' => $name,
                'is_staff' => $this->isFromStaff(),
            ];
        });
    }

    /**
     * Step context for the UI hint: the step the application was at when this
     * message was posted, with its label, plus the next incomplete step (null
     * once the application is live / everything is complete).
     */
    protected function stepContext(): Attribute
    {
        return Attribute::get(function (): ?array {
            if (is_null($this->current_step)) {
                return null;
            }

            $timeline = $this->application?->status?->ordered_timeline ?? [];

            $nextStep = collect($timeline)->first(fn ($step) => ! $step['is_completed']);

            return [
                'step' => $this->current_step,
                'step_label' => $timeline[$this->current_step]['label'] ?? $this->current_step,
                'next_step_label' => $nextStep['label'] ?? null,
            ];
        });
    }
}
