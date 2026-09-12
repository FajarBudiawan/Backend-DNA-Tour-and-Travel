<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationEquipment extends Model
{
    use HasUuids;

    protected $table = 'registration_equipments';

    // Tabel ini tidak memiliki created_at dan updated_at
    public $timestamps = false;

    protected $fillable = [
        'registration_id',
        'stock_id',
        'equipment_name',
        'size',
        'is_received',
        'received_at',
    ];

    protected $casts = [
        'is_received' => 'boolean',
        'received_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Pendaftaran pemilik perlengkapan
    public function registration()
    {
        return $this->belongsTo(Registration::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}