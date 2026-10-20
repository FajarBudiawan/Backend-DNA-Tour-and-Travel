<?php

namespace App\Listeners;

use App\Events\SosStatusUpdated;
use App\Jobs\ProcessSosNotificationJob;
use App\Models\Jamaah;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendSosStatusUpdatedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Timeout untuk listener execution
     */
    public $timeout = 30;

    /**
     * Jumlah retry jika gagal
     */
    public $tries = 2;

    /**
     * Handle the event.
     */
    public function handle(SosStatusUpdated $event): void
    {
        try {
            // Refresh incident
            $incident = $event->incident->fresh(['jamaah']);
            $jamaahName = $incident->jamaah?->full_name ?? 'Unknown';

            $statusesToNotify = ['acknowledged', 'in_action', 'resolved', 'false_alarm'];

            if (!in_array($event->newStatus, $statusesToNotify)) {
                Log::info('SOS status update - notification skipped', [
                    'new_status' => $event->newStatus,
                ]);
                return;
            }

            // Custom message per status
            $messages = [
                'acknowledged' => "Laporan darurat Anda telah diterima dan sedang ditangani.",
                'in_action' => "Tim kami sedang melakukan penanganan darurat Anda. Mohon tetap di lokasi yang aman.",
                'resolved' => "Laporan darurat Anda telah selesai ditangani.",
                'false_alarm' => $event->reason
                    ? "Laporan SOS ditandai sebagai alarm palsu: {$event->reason}"
                    : "Laporan darurat Anda telah ditandai sebagai alarm palsu / tidak sengaja tertekan.",
            ];

            $message = $messages[$event->newStatus] ?? "Status laporan darurat Anda berubah menjadi {$event->newStatus}";

            try {
                $notification = Notification::create([
                    'sos_incident_id' => $incident->id,
                    'recipient_type' => Jamaah::class,
                    'recipient_id' => $incident->jamaah_id,
                    'type' => 'sos_status_updated',
                    'title' => "Status Laporan: {$event->newStatus}",
                    'message' => $message,
                    'delivery_status' => 'pending',
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create status update notification', [
                    'jamaah_id' => $incident->jamaah_id,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }

            // Dispatch job dengan ID notifikasi yang baru dibuat
            dispatch(new ProcessSosNotificationJob($incident, [$notification->id]));

            Log::info('SOS status updated notification queued', [
                'incident_id' => $incident->id,
                'new_status' => $event->newStatus,
                'notification_id' => $notification->id,
            ]);
        } catch (\Exception $e) {
            Log::error('SendSosStatusUpdatedNotification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(SosStatusUpdated $event, \Throwable $exception): void
    {
        Log::critical('SendSosStatusUpdatedNotification listener failed', [
            'incident_id' => $event->incident->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}