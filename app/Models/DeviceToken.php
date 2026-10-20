<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceToken extends Model
{
    use HasUuids;

    protected $table = 'user_device_tokens';

    protected $keyType = 'string';
    public $incrementing = false;

    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_IOS = 'ios';

    public const PLATFORMS = [
        self::PLATFORM_ANDROID,
        self::PLATFORM_IOS,
    ];

    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'fcm_token',
        'device_id',
        'platform',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_used_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    /**
     * Relasi polymorphic ke pemilik token (Jamaah, InternalUser, atau TourLeader).
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope query untuk mengambil token perangkat yang aktif saja.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDeviceDelivery::class);
    }
}
