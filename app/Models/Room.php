<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasUuids;

    protected $table = 'rooms';

    protected $fillable = [
        'kloter_id',
        'hotel_id',
        'room_number',
        'room_type',
        'capacity',
        'gender',
        'notes',
    ];

    protected $appends = [
        'occupancy',
        'is_full',
    ];

    public function getOccupancyAttribute(): int
    {
        return $this->members()->count();
    }

    public function getIsFullAttribute(): bool
    {
        return $this->occupancy >= $this->capacity;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function kloter()
    {
        return $this->belongsTo(Kloter::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function members()
    {
        return $this->hasMany(RoomMember::class);
    }

    public function registrations()
    {
        return $this->belongsToMany(
            Registration::class,
            'room_members',
            'room_id',
            'registration_id'
        );
    }
}
