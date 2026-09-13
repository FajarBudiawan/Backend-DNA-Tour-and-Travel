<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class OtherIncome extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'other_incomes';

    protected $fillable = [
        'source',
        'category',
        'amount',
        'payment_method',
        'income_date',
        'reference_number',
        'notes',
        'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'income_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Generate reference number otomatis:
     * INC-YYYY-XXX
     */
    protected static function booted(): void
    {
        static::creating(function (OtherIncome $income) {
            if (empty($income->reference_number)) {
                $year = date('Y');
                $prefix = "INC-{$year}-";

                $lastIncome = static::withTrashed()
                    ->where('reference_number', 'like', "{$prefix}%")
                    ->orderBy('reference_number', 'desc')
                    ->first();

                $sequence = 1;

                if (
                    $lastIncome &&
                    preg_match(
                        '/INC-\d{4}-(\d+)/',
                        $lastIncome->reference_number,
                        $matches
                    )
                ) {
                    $sequence = (int) $matches[1] + 1;
                }

                $income->reference_number = sprintf(
                    'INC-%s-%03d',
                    $year,
                    $sequence
                );
            }
        });
    }

    /**
     * Admin yang mencatat pemasukan.
     */
    public function recordedBy()
    {
        return $this->belongsTo(
            InternalUser::class,
            'recorded_by'
        );
    }
}