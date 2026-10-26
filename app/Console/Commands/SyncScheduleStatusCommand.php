<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Services\ScheduleTimelineService;
use Illuminate\Console\Command;

class SyncScheduleStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schedules:sync-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate and log current timeline schedules status without overriding manual modifications.';

    /**
     * Execute the console command.
     */
    public function handle(ScheduleTimelineService $timelineService): int
    {
        $schedules = Schedule::orderBy('date')->orderBy('time')->get();

        if ($schedules->isEmpty()) {
            $this->info('No schedules found to synchronize.');
            return 0;
        }

        $resolved = $timelineService->resolveTimeline($schedules);

        $this->table(
            ['ID', 'Date', 'Time', 'Effective End', 'Title', 'Mode', 'Effective Status'],
            $resolved->map(function ($item) {
                $s = $item['schedule'];
                return [
                    $s->id,
                    $s->date instanceof \Carbon\Carbon ? $s->date->format('Y-m-d') : $s->date,
                    $s->time,
                    $item['effective_end_time'] ?? '-',
                    $s->title,
                    $s->status_mode ?? 'auto',
                    $item['status'],
                ];
            })
        );

        return 0;
    }
}
