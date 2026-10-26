<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'day_number',
        'date',
        'time',
        'end_time',
        'title',
        'category',
        'location',
        'keterangan',
        'pic',
        'status_override',
        'status_mode',
        'status_updated_by_type',
        'status_updated_by_id',
        'status_updated_at',
        'reminder_status',
        'broadcasted_at',
        'broadcasted_by_type',
        'broadcasted_by_id',
    ];

    protected $casts = [
        'date' => 'date',
        'status_updated_at' => 'datetime',
        'broadcasted_at' => 'datetime',
    ];
}
