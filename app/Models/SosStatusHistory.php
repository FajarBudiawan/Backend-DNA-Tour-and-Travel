<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosStatusHistory extends Model
{
    use HasUuids;

    protected $table = 'sos_status_histories';

    public $timestamps = false;

    protected $fillable = [
        'sos_incident_id',
        'old_status',
        'new_status',
        'changed_by',
        'tour_leader_id',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function sosIncident(): BelongsTo
    {
        return $this->belongsTo(SosIncident::class, 'sos_incident_id');
    }

    public function internalUser(): BelongsTo
    {
        return $this->belongsTo(InternalUser::class, 'changed_by');
    }

    public function tourLeader(): BelongsTo
    {
        return $this->belongsTo(TourLeader::class, 'tour_leader_id');
    }
}