<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosResponse extends Model
{
    use HasUuids;

    protected $table = 'sos_responses';

    public $timestamps = false;

    protected $fillable = [
        'sos_incident_id',
        'jamaah_id',
        'tour_leader_id',
        'internal_user_id',
        'message',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function sosIncident(): BelongsTo
    {
        return $this->belongsTo(SosIncident::class, 'sos_incident_id');
    }

    public function jamaah(): BelongsTo
    {
        return $this->belongsTo(Jamaah::class, 'jamaah_id');
    }

    public function tourLeader(): BelongsTo
    {
        return $this->belongsTo(TourLeader::class, 'tour_leader_id');
    }

    public function internalUser(): BelongsTo
    {
        return $this->belongsTo(InternalUser::class, 'internal_user_id');
    }
}