<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JamaahScheduleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'day_number' => $this->day_number,
            'activity_date' => $this->activity_date?->format('Y-m-d'),
            'activity_time' => $this->activity_time,
            'hijri_date_ref' => $this->hijri_date_ref,
            'title' => $this->title,
            'location' => $this->location,
            'category' => $this->category,
            'pic' => $this->pic,
            'status' => $this->status,
            'description' => $this->description,
            'is_published' => $this->is_published,
        ];
    }
}
