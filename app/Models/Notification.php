<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Notification extends Model
{
    use HasUuids;
    
    // Table name (default: notifications)
    protected $table = 'notifications';
    
    // Fillable columns (bisa di-mass assign)
    protected $fillable = [
        'sos_incident_id',
        'recipient_type',
        'recipient_id',
        'type',
        'title',
        'message',
        'read_at',
        'sent_at',
        'delivery_status',
        'read_at',
    ];
    
    // Cast columns ke tipe yang tepat
    protected $casts = [
        'read_at' => 'datetime',      // Cast ke Carbon datetime
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // RELATIONSHIP: Polymorphic
    // Bisa return InternalUser, TourLeader, atau Jamaah
    public function recipient()
    {
        return $this->morphTo();
    }
    
    // RELATIONSHIP: Belong to SOS Incident
    public function sosIncident()
    {
        return $this->belongsTo(SosIncident::class);
    }
    
    // SCOPE: Get unread notifications
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
    
    // SCOPE: Get notif by specific recipient
    public function scopeByRecipient($query, string $recipientType, string $recipientId)
    {
        return $query->where('recipient_type', $recipientType)
                     ->where('recipient_id', $recipientId);
    }
    
    // SCOPE: Get notif for SOS incident
    public function scopeByIncident($query, string $incidentId)
    {
        return $query->where('sos_incident_id', $incidentId);
    }

    /**
    * Cek apakah notifikasi sudah dibaca
    */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Mark notifikasi sebagai read
     */
    public function markAsRead(): bool
    {
        return $this->update(['read_at' => now()]);
    }
}