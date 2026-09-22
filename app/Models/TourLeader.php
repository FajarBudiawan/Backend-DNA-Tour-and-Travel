<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class TourLeader extends Authenticatable
{
    use HasApiTokens,HasUuids;
    
    protected $fillable = [
        'login_id',
        'full_name',
        'certification_number',
        'phone',
        'experience',
        'performance',
        'status',
    ];

    public function kloters(): BelongsToMany
    {
        return $this->belongsToMany(
            Kloter::class,
            'kloter_leader_assignments',
            'tour_leader_id',
            'kloter_id'
        )->withPivot('assigned_at');
    }
}