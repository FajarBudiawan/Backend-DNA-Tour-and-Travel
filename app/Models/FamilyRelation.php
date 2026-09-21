<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyRelation extends Model
{
    use HasUuids;

    protected $table = 'family_relations';

    const UPDATED_AT = null;

    protected $fillable = [
        'jamaah_id',
        'name',
        'relation_type',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Jamaah yang memiliki relasi keluarga ini.
     */
    public function jamaah(): BelongsTo
    {
        return $this->belongsTo(Jamaah::class, 'jamaah_id');
    }
}
