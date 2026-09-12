<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'quantity' => $this->quantity,
            'min_stock' => $this->min_stock,
            'unit' => $this->unit,
            'location' => $this->location,
            'notes' => $this->notes,

            'status' => $this->getStatus(),

            'sizes' => StockSizeResource::collection(
                $this->whenLoaded('sizes')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function getStatus(): string
    {
        if ($this->quantity === 0) {
            return 'Habis';
        }

        if ($this->quantity <= $this->min_stock) {
            return 'Menipis';
        }

        return 'Aman';
    }
}