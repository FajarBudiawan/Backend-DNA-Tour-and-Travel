<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    protected $fillable = [
        'day_number',
        'date',
        'time',
        'title',
        'category',
        'location',
        'keterangan',
        'pic',
        'status_override',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
