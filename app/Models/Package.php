<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Package extends Model
{
    use HasUuids;

    protected $table = 'packages';

    protected $fillable = [
        'name',
        'category',
        'status',
        'is_featured',
        'published_at',
        'created_by',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Admin yang membuat paket
    public function createdBy()
    {
        return $this->belongsTo(InternalUser::class, 'created_by');
    }

    // Kloter yang menggunakan paket ini
    public function kloters()
    {
        return $this->hasMany(Kloter::class);
    }

    // Pendaftaran yang memilih paket ini
    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }
}