<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Services\ScheduleTimelineService;

class ScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Support either wrapped array from ScheduleTimelineService or Schedule model directly
        $schedule = $this->resource;
        $inferredEndTime = null;
        $effectiveEndTime = null;
        $effectiveEndDateTime = null;
        $status = null;

        if (is_array($schedule) && isset($schedule['schedule'])) {
            $status = $schedule['status'];
            $inferredEndTime = $schedule['inferred_end_time'];
            $effectiveEndTime = $schedule['effective_end_time'];
            $effectiveEndDateTime = $schedule['effective_end_datetime'];
            $schedule = $schedule['schedule'];
        } else {
            $resolved = app(ScheduleTimelineService::class)->resolveSingle($schedule);
            $status = $resolved['status'];
            $inferredEndTime = $resolved['inferred_end_time'];
            $effectiveEndTime = $resolved['effective_end_time'];
            $effectiveEndDateTime = $resolved['effective_end_datetime'];
        }

        $dateFormatted = $schedule->date instanceof \Carbon\Carbon
            ? $schedule->date->format('Y-m-d')
            : (string) $schedule->date;

        return [
            'id' => $schedule->id,
            'day_number' => $schedule->day_number,
            'date' => $dateFormatted,
            'time' => $schedule->time,
            'end_time' => $schedule->end_time,
            'inferred_end_time' => $inferredEndTime,
            'effective_end_time' => $effectiveEndTime,
            'effective_end_datetime' => $effectiveEndDateTime,
            'title' => $schedule->title,
            'category' => $schedule->category,
            'location' => $schedule->location,
            'keterangan' => $schedule->keterangan,
            'pic' => $schedule->pic,
            'status' => $status,
            'status_override' => $schedule->status_override,
            'status_mode' => $schedule->status_mode ?? 'auto',
            'status_updated_by_type' => $schedule->status_updated_by_type,
            'status_updated_by_id' => $schedule->status_updated_by_id,
            'status_updated_at' => $schedule->status_updated_at?->toIso8601String(),
            'reminder_status' => $schedule->reminder_status ?? 'draft',
            'broadcasted_at' => $schedule->broadcasted_at?->toIso8601String(),
            'broadcasted_by_type' => $schedule->broadcasted_by_type,
            'broadcasted_by_id' => $schedule->broadcasted_by_id,
            'created_at' => $schedule->created_at?->toIso8601String(),
            'updated_at' => $schedule->updated_at?->toIso8601String(),
        ];
    }
}
