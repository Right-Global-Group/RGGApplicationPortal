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

    protected $guard = 'account';
    protected $guard_name = 'account';

    protected $fillable = [
        'name',
        'recipient_name',
        'email',
        'mobile',
        'user_id',
        'link_sent_at',
        'first_login_at',
        'photo_path',
    ];

    protected $hidden = [
        'remember_token',
    ];

    /**
     * `link_sent_at` is when we last mailed this merchant a login link; `first_login_at`
     * is when they last clicked one for the first time. Nothing else on the delivery path
     * is observable, so together they are the only way to tell mailed-but-never-reached
     * from reached-but-idle.
     */
    protected $casts = [
        'link_sent_at' => 'datetime',
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

    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }
}