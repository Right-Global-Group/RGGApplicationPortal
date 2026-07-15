<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Account extends Authenticatable
{
    use SoftDeletes, Notifiable, HasRoles;

    const STATUS_PENDING = 0;
    const STATUS_CONFIRMED = 1;

    protected $guard = 'account';
    protected $guard_name = 'account';

    protected $fillable = [
        'name',
        'recipient_name',
        'email',
        'mobile',
        'user_id',
        'status',
        'credentials_sent_at',
        'first_login_at',
        'photo_path',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected $casts = [
        'status' => 'integer',
        'credentials_sent_at' => 'datetime',
        'first_login_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function applications()
    {
        return $this->hasMany(Application::class);
    }    

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function emailReminders(): MorphMany
    {
        return $this->morphMany(EmailReminder::class, 'remindable');
    }
    
    public function emailLogs(): MorphMany
    {
        return $this->morphMany(EmailLog::class, 'emailable');
    }

    public function scopeOrderByName($query)
    {
        return $query->orderBy('name');
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where('name', 'like', '%'.$search.'%');
        });
        
        $query->when($filters['trashed'] ?? null, function ($query, $trashed) {
            if ($trashed === 'with') {
                $query->withTrashed();
            } elseif ($trashed === 'only') {
                $query->onlyTrashed();
            }
        });
    }

    public function isConfirmed(): bool
    {
        return $this->first_login_at !== null;
    }

    public function markAsConfirmed(): void
    {
        if (!$this->first_login_at) {
            $this->update([
                'status' => self::STATUS_CONFIRMED,
                'first_login_at' => now(),
            ]);
        }
    }

    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }
}