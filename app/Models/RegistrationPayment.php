<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;


class RegistrationPayment extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'registration_payments';

    // Tabel hanya memiliki created_at, tidak memiliki updated_at
    public $timestamps = false;

    protected $fillable = [
        'registration_id',
        'reference_number',
        'amount',
        'payment_type',
        'payment_method',
        'payment_date',
        'recorded_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'created_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Pendaftaran yang memiliki pembayaran ini
    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    // Admin yang mencatat pembayaran
    public function recordedBy()
    {
        return $this->belongsTo(InternalUser::class, 'recorded_by');
    }
}