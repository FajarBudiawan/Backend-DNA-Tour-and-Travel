<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ScheduleTimelineService
{
    /**
     * The timezone in which schedule date and time values are stored.
     * Dates and times entered by Web Admin are in WIB (Asia/Jakarta, UTC+7).
     * This constant ensures Carbon parses them correctly regardless of APP_TIMEZONE.
     */
    private const SCHEDULE_TIMEZONE = 'Asia/Jakarta';

    /**
     * Resolve effective timeline data for a collection of schedules.
     * Computes inferred end time and effective status without mutating database records.
     *
     * @param Collection<int, Schedule> $schedules
     * @param Carbon|null $currentTime
     * @return Collection<int, array>
     */
    public function resolveTimeline(Collection $schedules, ?Carbon $currentTime = null): Collection
    {
        $now = $currentTime ?? Carbon::now();

        // Sort chronologically by date and start time
        $sorted = $schedules->sort(function (Schedule $a, Schedule $b) {
            $dateA = $a->date instanceof Carbon ? $a->date->format('Y-m-d') : (string) $a->date;
            $dateB = $b->date instanceof Carbon ? $b->date->format('Y-m-d') : (string) $b->date;
            $dtA = $dateA . ' ' . $a->time;
            $dtB = $dateB . ' ' . $b->time;

            if ($dtA === $dtB) {
                return $a->id <=> $b->id;
            }

            return $dtA <=> $dtB;
        })->values();

        $resolved = collect();

        foreach ($sorted as $index => $schedule) {
            $dateStr = $schedule->date instanceof Carbon ? $schedule->date->format('Y-m-d') : (string) $schedule->date;
            $startDateTime = Carbon::parse($dateStr . ' ' . $schedule->time, self::SCHEDULE_TIMEZONE);

            $inferredEndTime = null;
            $inferredEndDateTime = null;

            if (!empty($schedule->end_time)) {
                $endDateTime = Carbon::parse($dateStr . ' ' . $schedule->end_time, self::SCHEDULE_TIMEZONE);
                // Handle crossing midnight if end_time < time
                if ($endDateTime->lt($startDateTime)) {
                    $endDateTime->addDay();
                }
                $effectiveEndDateTime = $endDateTime;
                $effectiveEndTime = $schedule->end_time;
            } else {
                // Infer from next schedule in sorted list
                $nextSchedule = $sorted->get($index + 1);
                if ($nextSchedule) {
                    $nextDateStr = $nextSchedule->date instanceof Carbon ? $nextSchedule->date->format('Y-m-d') : (string) $nextSchedule->date;
                    $nextStartDateTime = Carbon::parse($nextDateStr . ' ' . $nextSchedule->time, self::SCHEDULE_TIMEZONE);

                    if ($nextStartDateTime->gte($startDateTime)) {
                        $effectiveEndDateTime = $nextStartDateTime;
                        $inferredEndTime = $nextSchedule->time;
                        $inferredEndDateTime = $nextStartDateTime;
                        $effectiveEndTime = $nextSchedule->time;
                    } else {
                        $effectiveEndDateTime = null;
                        $effectiveEndTime = null;
                    }
                } else {
                    // No next schedule and end_time is empty -> do NOT invent artificial end time
                    $effectiveEndDateTime = null;
                    $effectiveEndTime = null;
                }
            }

            // Calculate status
            if ($schedule->status_mode === 'manual' && !empty($schedule->status_override)) {
                $status = $schedule->status_override;
            } else {
                if ($now->lt($startDateTime)) {
                    $status = 'upcoming';
                } elseif ($effectiveEndDateTime !== null) {
                    if ($now->gte($effectiveEndDateTime)) {
                        $status = 'completed';
                    } else {
                        $status = 'in_progress';
                    }
                } else {
                    // Start time has arrived, no end time available -> in_progress
                    $status = 'in_progress';
                }
            }

            $resolved->push([
                'schedule' => $schedule,
                'status' => $status,
                'inferred_end_time' => $inferredEndTime,
                'effective_end_time' => $effectiveEndTime,
                'effective_end_datetime' => $effectiveEndDateTime?->toIso8601String(),
            ]);
        }

        return $resolved;
    }

    /**
     * Resolve a single schedule's status and inferred end time in context of other schedules.
     */
    public function resolveSingle(Schedule $schedule, ?Carbon $currentTime = null): array
    {
        // To accurately resolve inferred end time, fetch surrounding schedule context
        $dateStr = $schedule->date instanceof Carbon ? $schedule->date->format('Y-m-d') : (string) $schedule->date;
        $all = Schedule::orderBy('date')->orderBy('time')->get();

        $resolved = $this->resolveTimeline($all, $currentTime);

        $found = $resolved->first(fn ($item) => $item['schedule']->id === $schedule->id);

        if ($found) {
            return $found;
        }

        // Fallback if not found in list (e.g. unpersisted)
        return $this->resolveTimeline(collect([$schedule]), $currentTime)->first();
    }
}
