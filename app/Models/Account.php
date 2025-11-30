<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class Account extends Authenticatable
{
    use SoftDeletes, Notifiable, HasRoles;

    const STATUS_PENDING = 0;
    const STATUS_CONFIRMED = 1;

    protected $guard = 'account';

    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'user_id',
        'status',
        'credentials_sent_at',
        'first_login_at',
        'photo_path',
    ];

    protected $hidden = [
        'password',
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
        return $this->status === self::STATUS_CONFIRMED;
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

    public static function generatePassword(): string
    {
        return Str::random(12);
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }
}