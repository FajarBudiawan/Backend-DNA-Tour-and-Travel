<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Mutawif extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'name',
        'language',
        'experience',
        'status',
    ];

    public function kloters(): BelongsToMany
    {
        return $this->belongsToMany(
            Kloter::class,
            'mutawif_kloter_assignments',
            'mutawif_id',
            'kloter_id'
        )->withPivot('assigned_at');
    }
}