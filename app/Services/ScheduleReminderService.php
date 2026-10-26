<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\FamilyRelation;
use App\Models\InternalUser;
use App\Models\Jamaah;
use App\Models\Notification;
use App\Models\Schedule;
use App\Models\TourLeader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ScheduleReminderService
{
    /**
     * Identify all valid, deduplicated recipients for a global schedule reminder.
     * Rules:
     * - Active Jamaah accounts
     * - Active Tour Leaders
     * - Active Admins (InternalUser)
     * - Active Family members who are related to an active Jamaah
     *
     * @return Collection<int, array{recipient_type: string, recipient_id: string, name: string}>
     */
    public function getEligibleRecipients(): Collection
    {
        $recipients = collect();

        // 1. Active Jamaah
        $activeJamaahs = Jamaah::where('status', 'active')->get();
        foreach ($activeJamaahs as $jamaah) {
            $recipients->push([
                'recipient_type' => Jamaah::class,
                'recipient_id' => (string) $jamaah->id,
                'name' => $jamaah->full_name ?? 'Jamaah',
            ]);
        }

        // 2. Active Tour Leaders
        $activeTourLeaders = TourLeader::where('status', 'active')->get();
        foreach ($activeTourLeaders as $tl) {
            $recipients->push([
                'recipient_type' => TourLeader::class,
                'recipient_id' => (string) $tl->id,
                'name' => $tl->full_name ?? 'Tour Leader',
            ]);
        }

        // 3. Active Admins
        $activeAdmins = InternalUser::where('status', 'active')->get();
        foreach ($activeAdmins as $admin) {
            $recipients->push([
                'recipient_type' => InternalUser::class,
                'recipient_id' => (string) $admin->id,
                'name' => $admin->full_name ?? 'Admin',
            ]);
        }

        // 4. Family members:
        // In DNA Tour architecture, Family members login using Jamaah login_id and register device tokens
        // under tokenable_type = Jamaah::class.
        // Also family_relations table stores family member info with status 'active' linked to jamaah_id.
        // Only active family relations linked to active Jamaah qualify!
        // We ensure deduplication so each recipient_type + recipient_id receives at most 1 notification.
        return $recipients->unique(function ($item) {
            return $item['recipient_type'] . ':' . $item['recipient_id'];
        })->values();
    }

    /**
     * Verify if a specific family relation qualifies for reminder.
     */
    public function isFamilyEligible(FamilyRelation $familyRelation): bool
    {
        if ($familyRelation->status !== 'active') {
            return false;
        }

        $jamaah = $familyRelation->jamaah;
        if (!$jamaah || $jamaah->status !== 'active') {
            return false;
        }

        return true;
    }

    /**
     * Broadcast schedule reminder:
     * - Record broadcast metadata on Schedule (idempotent / safe against repeated broadcast)
     * - Create notification records for all eligible recipients
     * - Dispatch queued jobs or process delivery
     *
     * @param Schedule $schedule
     * @param mixed $actor The user/tour leader initiating the broadcast
     * @return array
     */
    public function broadcast(Schedule $schedule, $actor): array
    {
        $actorClass = get_class($actor);
        $actorId = (string) $actor->id;

        // Update schedule to published & record broadcast audit
        $schedule->update([
            'reminder_status' => 'published',
            'broadcasted_at' => now(),
            'broadcasted_by_type' => $actorClass,
            'broadcasted_by_id' => $actorId,
        ]);

        $recipients = $this->getEligibleRecipients();

        $title = "Pengingat Jadwal: {$schedule->title}";
        $timeStr = $schedule->time . ($schedule->end_time ? " - {$schedule->end_time}" : '');
        $message = "Kegiatan {$schedule->title} dijadwalkan pada {$schedule->date?->format('d/m/Y')} pukul {$timeStr} di {$schedule->location}.";

        $createdNotificationIds = [];

        foreach ($recipients as $recipient) {
            // Deduplicate: avoid creating duplicate notification if already created for this schedule & recipient within last 1 hour
            $existing = Notification::where('recipient_type', $recipient['recipient_type'])
                ->where('recipient_id', $recipient['recipient_id'])
                ->where('type', 'schedule_reminder')
                ->where('title', $title)
                ->where('created_at', '>=', now()->subHour())
                ->first();

            if ($existing) {
                $createdNotificationIds[] = $existing->id;
                continue;
            }

            $notification = Notification::create([
                'recipient_type' => $recipient['recipient_type'],
                'recipient_id' => $recipient['recipient_id'],
                'type' => 'schedule_reminder',
                'title' => $title,
                'message' => $message,
                'delivery_status' => 'pending',
            ]);

            $createdNotificationIds[] = $notification->id;
        }

        Log::info('Schedule reminder broadcast initiated', [
            'schedule_id' => $schedule->id,
            'broadcasted_by' => "{$actorClass}:{$actorId}",
            'recipients_count' => $recipients->count(),
            'notifications_created' => count($createdNotificationIds),
        ]);

        // Dispatch background delivery if notifications exist
        if (!empty($createdNotificationIds)) {
            dispatch(new \App\Jobs\ProcessScheduleReminderJob($schedule, $createdNotificationIds));
        }

        return [
            'schedule_id' => $schedule->id,
            'reminder_status' => 'published',
            'broadcasted_at' => $schedule->broadcasted_at?->toIso8601String(),
            'broadcasted_by_type' => $actorClass,
            'broadcasted_by_id' => $actorId,
            'recipients_count' => $recipients->count(),
            'notifications_queued' => count($createdNotificationIds),
        ];
    }
}
