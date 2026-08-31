<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'category', 'quantity', 'min_stock', 'unit', 'location', 'notes'];
    protected $casts = ['quantity' => 'integer', 'min_stock' => 'integer'];
    protected $appends = ['stock_status'];

    public function getStockStatusAttribute(): string
    {
        return $this->quantity === 0 ? 'out_of_stock' : ($this->quantity <= $this->min_stock ? 'low' : 'safe');
    }
}
