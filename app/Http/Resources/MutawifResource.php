<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MutawifResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'language' => $this->language,
            'experience' => $this->experience,
            'status' => $this->status,

            'kloters' => $this->whenLoaded('kloters', function () {
                return $this->kloters->map(function ($kloter) {
                    return [
                        'id' => $kloter->id,
                        'name' => $kloter->name,
                        'code' => $kloter->code,
                        'assigned_at' => $kloter->pivot->assigned_at,
                    ];
                });
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}