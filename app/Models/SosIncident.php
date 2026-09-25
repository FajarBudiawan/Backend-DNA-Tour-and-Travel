<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SosIncident extends Model
{
    use HasUuids;

    protected $table = 'sos_incidents';

    public $timestamps = false;

    protected $fillable = [
        'jamaah_id',
        'kloter_id',
        'type',
        'description',
        'location_name',
        'latitude',
        'longitude',
        'status',
        'triggered_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'triggered_at' => 'datetime',
    ];

    public function jamaah(): BelongsTo
    {
        return $this->belongsTo(Jamaah::class, 'jamaah_id');
    }

    public function kloter(): BelongsTo
    {
        return $this->belongsTo(Kloter::class, 'kloter_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(SosStatusHistory::class, 'sos_incident_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SosResponse::class, 'sos_incident_id');
    }
}