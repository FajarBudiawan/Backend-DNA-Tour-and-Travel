<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scheduleDateTime = Carbon::parse(
            $this->date->format('Y-m-d') . ' ' . $this->time
        );

        if ($this->status_override) {
            $status = $this->status_override;
        } elseif (now()->lt($scheduleDateTime)) {
            $status = 'upcoming';
        } else {
            $status = 'completed';
        }

        return [
            'id' => $this->id,
            'day_number' => $this->day_number,
            'date' => $this->date?->format('Y-m-d'),
            'time' => $this->time,
            'title' => $this->title,
            'category' => $this->category,
            'location' => $this->location,
            'keterangan' => $this->keterangan,
            'pic' => $this->pic,
            'status' => $status,
            'status_override' => $this->status_override,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}