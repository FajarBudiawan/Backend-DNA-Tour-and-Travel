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
        'login_id',
        'nik',
        'full_name',
        'birth_date',
        'gender',
        'phone',
        'emergency_contact',
        'status',
        'archived_at',
        'created_by',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            InternalUser::class,
            'created_by'
        );
    }
}