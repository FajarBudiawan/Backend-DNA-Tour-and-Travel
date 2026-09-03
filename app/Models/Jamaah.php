<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Jamaah extends Model
{
    use HasUuids;

    protected $table = 'jamaah';

    protected $fillable = [
        // Identitas Login
        'login_id',

        // Identitas Pribadi
        'nik',
        'full_name',
        'birth_date',
        'gender',
        'phone',
        'emergency_contact',

        // Dokumen Perjalanan
        'passport_number',
        'visa_number',
        'nationality',

        // Relasi Paket & Kloter
        'package_id',
        'kloter_id',

        // Logistik Perjalanan
        'hotel_makkah',
        'hotel_madinah',
        'departure_date',
        'return_date',

        // Pembimbing (plain text)
        'tour_leader',
        'mutawif_local',

        // Status
        'status',
        'archived_at',
        'created_by',
    ];

    protected $casts = [
        'birth_date'     => 'date',
        'departure_date' => 'date',
        'return_date'    => 'date',
        'archived_at'    => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(InternalUser::class, 'created_by');
    }

    /**
     * Paket umrah yang dimiliki Jamaah ini.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'package_id');
    }

    /**
     * Kloter keberangkatan Jamaah ini.
     */
    public function kloter(): BelongsTo
    {
        return $this->belongsTo(Kloter::class, 'kloter_id');
    }
}