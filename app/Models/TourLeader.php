<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TourLeader extends Model
{
    use HasUuids;

    protected $fillable = ['login_id', 'full_name', 'certification_number', 'phone', 'status', 'experience', 'performance'];

    public function kloters()
    {
        return $this->belongsToMany(Kloter::class, 'kloter_leader_assignments')
            ->withPivot(['id', 'assigned_at']);
    }
}
