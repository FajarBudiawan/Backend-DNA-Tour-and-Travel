<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourLeaderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'login_id' => $this->login_id,
            'name' => $this->full_name,
            'phone' => $this->phone,
            'certification_number' => $this->certification_number,
            'experience' => $this->experience,
            'performance' => $this->performance,
            'status' => $this->status,

            'kloters' => $this->whenLoaded('kloters', function () {
                return $this->kloters->map(function ($kloter) {
                    return [
                        'id' => $kloter->id,
                        'name' => $kloter->name,
                        'code' => $kloter->code,
                    ];
                });
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}