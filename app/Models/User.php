<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'owner',
        'photo_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'owner' => 'boolean',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
    
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
    
    public function scopeOrderByName($query)
    {
        return $query->orderBy('first_name')->orderBy('last_name');
    }

    public function scopeFilter($query, array $filters)
    {
        // Search by name or email
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });

        // Filter by role
        $query->when($filters['role'] ?? null, function ($query, $role) {
            $query->where('owner', $role);
        });

        // Filter trashed (soft deleted) users
        $query->when($filters['trashed'] ?? null, function ($query, $trashed) {
            if ($trashed === 'with') {
                $query->withTrashed();
            } elseif ($trashed === 'only') {
                $query->onlyTrashed();
            }
        });
    }

    public function isDemoUser(): bool
    {
        return $this->email === 'demo@example.com';
    }
}