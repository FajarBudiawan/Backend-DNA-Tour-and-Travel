<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Expense extends Model
{
    use HasUuids;

    protected $table = 'expenses';

    protected $fillable = [
        'vendor',
        'category',
        'amount',
        'payment_method',
        'expense_date',
        'reference_number',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
    ];

    /**
     * Boot function untuk auto-generate reference_number.
     */
    protected static function booted(): void
    {
        static::creating(function (Expense $expense) {
            if (empty($expense->reference_number)) {
                $year = date('Y');
                $prefix = "TRX-{$year}-";

                $lastExpense = static::where('reference_number', 'like', "{$prefix}%")
                    ->orderBy('reference_number', 'desc')
                    ->first();

                $sequence = 1;
                if ($lastExpense && preg_match('/TRX-\d{4}-(\d+)/', $lastExpense->reference_number, $matches)) {
                    $sequence = (int) $matches[1] + 1;
                }

                $expense->reference_number = sprintf('TRX-%s-%03d', $year, $sequence);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * User / Admin yang mencatat pengeluaran.
     */
    public function recordedBy()
    {
        return $this->belongsTo(InternalUser::class, 'recorded_by');
    }
}
