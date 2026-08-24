<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Registration extends Model
{
    use HasUuids;

    protected $table = 'registrations';

    protected $fillable = [
        'registration_number',
        'full_name',
        'passport_number',
        'nik',
        'phone',
        'birth_date',
        'gender',
        'registration_date',
        'departure_date',
        'package_id',
        'kloter_id',
        'meningitis_vaccine_status',
        'photo_status',
        'total_package_cost',
        'status',
        'created_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'registration_date' => 'date',
        'departure_date' => 'date',
        'total_package_cost' => 'decimal:2',
    ];

    protected $appends = [
        'total_paid',
        'remaining_cost',
    ];

    /*
    |--------------------------------------------------------------------------
    | Appended Financial Attributes
    |--------------------------------------------------------------------------
    */

    public function getTotalPaidAttribute(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function getRemainingCostAttribute(): float
    {
        $remaining = (float) $this->total_package_cost - $this->total_paid;
        return max(0, $remaining);
    }

    /**
     * Hitung dan perbarui status keuangan pendaftaran secara otomatis.
     */
    public function updateFinancialStatus(): void
    {
        if (in_array($this->status, ['converted', 'cancelled'])) {
            return;
        }

        $totalPaid = (float) $this->payments()->sum('amount');
        $totalCost = (float) $this->total_package_cost;

        if ($totalPaid >= $totalCost && $totalCost > 0) {
            $newStatus = 'fully_paid';
        } elseif ($totalPaid > 0) {
            $newStatus = 'dp_paid';
        } else {
            $newStatus = 'unpaid';
        }

        $this->status = $newStatus;
        $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Paket yang dipilih saat pendaftaran
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    // Kloter yang dipilih
    public function kloter()
    {
        return $this->belongsTo(Kloter::class);
    }

    // Admin yang membuat pendaftaran
    public function createdBy()
    {
        return $this->belongsTo(InternalUser::class, 'created_by');
    }

    // Riwayat pembayaran
    public function payments()
    {
        return $this->hasMany(RegistrationPayment::class);
    }

    // Perlengkapan pendaftaran
    public function equipments()
    {
        return $this->hasMany(RegistrationEquipment::class);
    }
}