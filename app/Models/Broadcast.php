<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Broadcast extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'broadcasts';

    protected $fillable = [
        'title',
        'sender_type',
        'sender_id',
        'message',
        'category',
        'priority',
        'target_type',
        'status',
        'idempotency_key',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Polymorphic sender: InternalUser atau TourLeader
     */
    public function sender()
    {
        return $this->morphTo();
    }

    /**
     * Target kloter yang ditugaskan (jika target_type === 'kloter')
     */
    public function recipientKloters(): BelongsToMany
    {
        return $this->belongsToMany(
            Kloter::class,
            'broadcast_recipient_kloters',
            'broadcast_id',
            'kloter_id'
        );
    }

    /**
     * Relasi ke notifikasi individual yang dihasilkan (fan-out)
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'broadcast_id');
    }

    /**
     * Relasi ke broadcast logs historis jika digunakan
     */
    public function logs(): HasMany
    {
        return $this->hasMany(BroadcastLog::class, 'broadcast_id');
    }
}
