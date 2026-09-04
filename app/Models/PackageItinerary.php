<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PackageItinerary extends Model
{
    use HasUuids;

    protected $table = 'package_itineraries';

    protected $fillable = [
        'package_id',
        'day_number',
        'title',
        'activity_time',
        'location',
        'category',
        'description',
    ];

    protected $casts = [
        'day_number' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function package()
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    public function kloterSchedules()
    {
        return $this->hasMany(KloterSchedule::class, 'source_itinerary_id');
    }
}
