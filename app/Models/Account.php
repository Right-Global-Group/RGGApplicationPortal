<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $fillable = ['name'];

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

    public function scopeOrderByName($query)
    {
        return $query->orderBy('name');
    }

    public function scopeFilter($query, array $filters)
    {
        // Search by name
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where('name', 'like', '%'.$search.'%');
        });
        // Filter trashed (soft deleted) accounts
        $query->when($filters['trashed'] ?? null, function ($query, $trashed) {
            if ($trashed === 'with') {
                $query->withTrashed();
            } elseif ($trashed === 'only') {
                $query->onlyTrashed();
            }
        });
    }
}