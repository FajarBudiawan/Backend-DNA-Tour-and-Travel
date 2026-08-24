<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    use HasUuids;

    protected $table = 'hotels';

    protected $fillable = [
        'name',
        'city',
        'star_rating',
        'distance_to_mosque',
        'contact_info',
        'geofence_zone_id',
        'status',
    ];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}
