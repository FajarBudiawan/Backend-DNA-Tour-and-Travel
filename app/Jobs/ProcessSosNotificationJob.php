<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\SosIncident;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSosNotificationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Timeout untuk job execution
     */
    public $timeout = 60;

    /**
     * Jumlah retry jika gagal
     */
    public $tries = 3;

    /**
     * Delay sebelum retry (seconds)
     */
    public $retryAfter = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public SosIncident $incident
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // 1. Fetch semua pending notifications untuk incident ini
            $notifications = Notification::where('sos_incident_id', $this->incident->id)
                ->where('delivery_status', 'pending')
                ->get();

            Log::info('Processing SOS notifications', [
                'incident_id' => $this->incident->id,
                'notification_count' => $notifications->count(),
            ]);

            // 2. Loop setiap notification
            foreach ($notifications as $notification) {
                try {
                    // Phase 2: Di sini nanti akan kirim ke Firebase
                    // Untuk sekarang: just mark as sent
                    $notification->update([
                        'sent_at' => now(),
                        'delivery_status' => 'sent',
                    ]);

                    Log::info('Notification processed', [
                        'notification_id' => $notification->id,
                        'recipient_type' => $notification->recipient_type,
                        'recipient_id' => $notification->recipient_id,
                    ]);
                } catch (\Exception $e) {
                    // Jika 1 notification gagal, log tapi continue
                    Log::warning('Failed to process single notification', [
                        'notification_id' => $notification->id,
                        'error' => $e->getMessage(),
                    ]);

                    $notification->update([
                        'delivery_status' => 'failed',
                    ]);
                }
            }

            Log::info('SOS notifications processed successfully', [
                'incident_id' => $this->incident->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Job failed', [
                'incident_id' => $this->incident->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;  // Will retry via queue
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('ProcessSosNotificationJob failed after retries', [
            'incident_id' => $this->incident->id,
            'exception' => $exception->getMessage(),
        ]);
        // TODO: Alert admin tentang job yang gagal
    }
}