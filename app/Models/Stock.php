<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;


class Stock extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'category',
        'quantity',
        'min_stock',
        'unit',
        'location',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'min_stock' => 'integer',
    ];

    public function sizes(): HasMany
    {
        return $this->hasMany(StockSize::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    public static function generateCode(): string
    {
        $number = DB::selectOne(
            "SELECT nextval('stock_code_seq') AS number"
        )->number;

        return 'STK-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(RegistrationEquipment::class);
    }
}