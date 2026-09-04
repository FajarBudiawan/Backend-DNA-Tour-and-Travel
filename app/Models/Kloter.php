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
        'tour_leader',
        'mutawif_local',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date'    => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Paket umrah yang terkait dengan kloter ini.
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Pendaftaran (CRM/Lead) yang memilih kloter ini.
     * CATATAN: Ini independen dari relasi Jamaah.
     */
    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Jamaah resmi yang ter-assign ke kloter ini.
     * Single source of truth: via jamaah.kloter_id (bukan kloter_members).
     *
     * @deprecated kloter_members pivot — sudah tidak dipakai, lihat catatan di migration.
     */
    public function jamaah()
    {
        return $this->hasMany(Jamaah::class, 'kloter_id');
    }

    /**
     * Hotel Makkah yang dipakai kloter ini (FK ke hotels).
     */
    public function hotelMakkah()
    {
        return $this->belongsTo(Hotel::class, 'hotel_makkah_id');
    }

    /**
     * Hotel Madinah yang dipakai kloter ini (FK ke hotels).
     */
    public function hotelMadinah()
    {
        return $this->belongsTo(Hotel::class, 'hotel_madinah_id');
    }

    /**
     * Rundown jadwal kegiatan kloter ini.
     */
    public function schedules()
    {
        return $this->hasMany(KloterSchedule::class, 'kloter_id');
    }
}