<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDeviceDelivery extends Model
{
    use HasUuids;

    protected $table = 'notification_device_deliveries';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'notification_id',
        'device_token_id',
        'status',
        'attempts',
        'sent_at',
        'last_error',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function deviceToken(): BelongsTo
    {
        return $this->belongsTo(DeviceToken::class);
    }
}
