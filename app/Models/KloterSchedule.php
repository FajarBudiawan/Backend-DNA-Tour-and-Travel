<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class KloterSchedule extends Model
{
    use HasUuids;

    protected $table = 'kloter_schedules';

    protected $fillable = [
        'kloter_id',
        'day_number',
        'activity_date',
        'activity_time',
        'hijri_date_ref',
        'title',
        'location',
        'category',
        'pic',
        'status',
        'description',
        'is_published',
        'source_itinerary_id',
        'is_customized',
    ];

    protected $casts = [
        'day_number' => 'integer',
        'activity_date' => 'date',
        'is_published' => 'boolean',
        'is_customized' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function kloter()
    {
        return $this->belongsTo(Kloter::class, 'kloter_id');
    }

    public function sourceItinerary()
    {
        return $this->belongsTo(PackageItinerary::class, 'source_itinerary_id');
    }
}
