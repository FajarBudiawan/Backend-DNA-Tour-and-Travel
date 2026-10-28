<?php

namespace App\Jobs;

use App\Exceptions\InvalidFcmTokenException;
use App\Models\Broadcast;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationDeviceDelivery;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBroadcastJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 60;
    public $tries = 3;
    public $retryAfter = 30;

    /**
     * @param Broadcast $broadcast
     * @param array<int, string> $notificationIds
     */
    public function __construct(
        public Broadcast $broadcast,
        public array $notificationIds = [],
    ) {
        // Alokasikan ke queue 'default' agar tidak mengganggu antrean SOS
        $this->onQueue('default');
    }

    public function handle(FcmService $fcm): void
    {
        if (empty($this->notificationIds)) {
            return;
        }

        $notifications = Notification::whereIn('id', $this->notificationIds)
            ->where('delivery_status', 'pending')
            ->get();

        if ($notifications->isEmpty()) {
            return;
        }

        $needsRetry = false;

        foreach ($notifications as $notification) {
            $tokens = DeviceToken::where('tokenable_type', $notification->recipient_type)
                ->where('tokenable_id', $notification->recipient_id)
                ->active()
                ->get();

            // Jika penerima belum memiliki token perangkat yang terdaftar
            if ($tokens->isEmpty()) {
                $notification->update([
                    'delivery_status' => 'sent', // Tetap dicatat sebagai terkirim ke inbox
                ]);
                continue;
            }

            $payload = [
                'title' => (string) $notification->title,
                'body' => (string) $notification->message,
                'data' => [
                    'notification_id' => (string) $notification->id,
                    'broadcast_id' => (string) $this->broadcast->id,
                    'priority' => (string) $this->broadcast->priority,
                    'category' => (string) $this->broadcast->category,
                    'type' => (string) $notification->type,
                ],
            ];

            $hasTransientFailure = false;
            $hasSuccess = false;

            foreach ($tokens as $deviceToken) {
                $delivery = NotificationDeviceDelivery::firstOrCreate(
                    [
                        'notification_id' => $notification->id,
                        'device_token_id' => $deviceToken->id,
                    ],
                    [
                        'status' => 'pending',
                        'attempts' => 0,
                    ]
                );

                // Idempotensi: jika token ini sudah sukses terkirim, lewati
                if ($delivery->status === 'sent') {
                    $hasSuccess = true;
                    continue;
                }

                $delivery->increment('attempts');

                try {
                    $fcm->send($deviceToken->fcm_token, $payload);
                    $hasSuccess = true;

                    $delivery->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                        'last_error' => null,
                    ]);

                    $deviceToken->update(['last_used_at' => now()]);
                } catch (InvalidFcmTokenException $e) {
                    // Token invalid / unregistered -> nonaktifkan token permanen
                    $deviceToken->update(['is_active' => false]);
                    $delivery->update([
                        'status' => 'failed',
                        'last_error' => $e->getMessage() ?: 'Token unregistered or invalid',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('FCM delivery failed for broadcast', [
                        'broadcast_id' => $this->broadcast->id,
                        'notification_id' => $notification->id,
                        'device_token_id' => $deviceToken->id,
                        'error' => $e->getMessage(),
                    ]);
                    $hasTransientFailure = true;
                    $needsRetry = true;
                    $delivery->update([
                        'status' => 'failed',
                        'last_error' => $e->getMessage(),
                    ]);
                }
            }

            // Perbarui status notifikasi individual
            if ($hasTransientFailure) {
                $notification->update(['delivery_status' => 'pending']);
            } elseif ($hasSuccess) {
                $notification->update([
                    'delivery_status' => 'sent',
                    'sent_at' => now(),
                ]);
            } else {
                $notification->update(['delivery_status' => 'failed']);
            }
        }

        if ($needsRetry) {
            throw new \RuntimeException('One or more broadcast deliveries need retry.');
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessBroadcastJob failed permanently', [
            'broadcast_id' => $this->broadcast->id,
            'error' => $exception->getMessage(),
        ]);

        NotificationDeviceDelivery::whereIn('notification_id', $this->notificationIds)
            ->where('status', '!=', 'sent')
            ->update([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);

        Notification::whereIn('id', $this->notificationIds)
            ->where('delivery_status', 'pending')
            ->update([
                'delivery_status' => 'failed',
            ]);
    }
}
