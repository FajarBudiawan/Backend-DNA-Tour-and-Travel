<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class RegistrationPayment extends Model
{
    use HasUuids;

    protected $table = 'registration_payments';

    // Tabel hanya memiliki created_at, tidak memiliki updated_at
    public $timestamps = false;

    protected $fillable = [
        'registration_id',
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