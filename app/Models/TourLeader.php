<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TourLeader extends Model
{
    use HasUuids;
    
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