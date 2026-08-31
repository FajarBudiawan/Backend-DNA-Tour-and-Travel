<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Kloter extends Model
{
    use HasUuids;

    protected $table = 'kloters';

    protected $fillable = [
        'name',
        'package_id',
        'code',
        'flight_code',
        'departure_date',
        'return_date',
        'hotel_makkah_id',
        'hotel_madinah_id',
        'status',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Paket yang dimiliki kloter
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    // Pendaftaran yang masuk ke kloter ini
    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function tourLeaders()
    {
        return $this->belongsToMany(TourLeader::class, 'kloter_leader_assignments')->withPivot(['id', 'assigned_at']);
    }

    public function muthawwifs()
    {
        return $this->belongsToMany(Muthawwif::class, 'kloter_muthawwif_assignments')->withPivot(['id', 'assigned_at']);
    }
}
