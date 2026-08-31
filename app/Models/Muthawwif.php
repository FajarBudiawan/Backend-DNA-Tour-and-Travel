<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Muthawwif extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'language', 'experience', 'status'];

    public function kloters()
    {
        return $this->belongsToMany(Kloter::class, 'kloter_muthawwif_assignments')
            ->withPivot(['id', 'assigned_at']);
    }
}
