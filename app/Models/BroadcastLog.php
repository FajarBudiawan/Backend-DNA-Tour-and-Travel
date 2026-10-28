<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastLog extends Model
{
    use HasUuids;

    protected $table = 'broadcast_logs';

    public $timestamps = false;

    protected $fillable = [
        'broadcast_id',
        'recipient_type',
        'recipient_id',
        'status',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class, 'broadcast_id');
    }
}
