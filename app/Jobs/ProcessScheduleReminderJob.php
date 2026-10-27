<?php

namespace App\Jobs;

use App\Exceptions\InvalidFcmTokenException;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationDeviceDelivery;
use App\Models\Schedule;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessScheduleReminderJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 60;
    public $tries = 3;
    public $retryAfter = 30;

    /**
     * @param Schedule $schedule
     * @param array<int, string> $notificationIds
     */
    public function __construct(
        public Schedule $schedule,
        public array $notificationIds = [],
    ) {
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

            if ($tokens->isEmpty()) {
                $notification->update([
                    'delivery_status' => 'sent', // Recorded and processed; no device token registered yet
                ]);
                continue;
            }

            $payload = [
                'title' => $notification->title,
                'body' => $notification->message,
                'data' => [
                    'notification_id' => $notification->id,
                    'schedule_id' => (string) $this->schedule->id,
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

                // If already successfully sent, skip (idempotency)
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
                    $deviceToken->update(['is_active' => false]);
                    $delivery->update([
                        'status' => 'failed',
                        'last_error' => $e->getMessage() ?: 'Token unregistered or invalid',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('FCM delivery failed for reminder', [
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

            // Update notification status based on delivery outcomes
            if ($hasTransientFailure) {
                // Keep pending so queue can retry transient deliveries
                $notification->update([
                    'delivery_status' => 'pending',
                ]);
            } elseif ($hasSuccess) {
                $notification->update([
                    'delivery_status' => 'sent',
                    'sent_at' => now(),
                ]);
            } else {
                $notification->update([
                    'delivery_status' => 'failed',
                ]);
            }
        }

        if ($needsRetry) {
            throw new \RuntimeException('One or more reminder deliveries need retry.');
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessScheduleReminderJob failed permanently', [
            'schedule_id' => $this->schedule->id,
            'error' => $exception->getMessage(),
        ]);

        // Mark remaining pending deliveries as failed
        NotificationDeviceDelivery::whereIn('notification_id', $this->notificationIds)
            ->where('status', '!=', 'sent')
            ->update([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
            ]);

        // Mark remaining pending notifications as failed
        Notification::whereIn('id', $this->notificationIds)
            ->where('delivery_status', 'pending')
            ->update([
                'delivery_status' => 'failed',
            ]);
    }
}
