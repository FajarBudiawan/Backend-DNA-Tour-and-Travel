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

            $anySent = false;
            foreach ($tokens as $deviceToken) {
                try {
                    $fcm->send($deviceToken->fcm_token, $payload);
                    $anySent = true;

                    NotificationDeviceDelivery::create([
                        'notification_id' => $notification->id,
                        'device_token_id' => $deviceToken->id,
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                } catch (InvalidFcmTokenException $e) {
                    $deviceToken->update(['is_active' => false]);
                    NotificationDeviceDelivery::create([
                        'notification_id' => $notification->id,
                        'device_token_id' => $deviceToken->id,
                        'status' => 'failed',
                        'error_message' => 'Token unregistered',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('FCM delivery failed for reminder', [
                        'notification_id' => $notification->id,
                        'error' => $e->getMessage(),
                    ]);
                    $needsRetry = true;
                    NotificationDeviceDelivery::create([
                        'notification_id' => $notification->id,
                        'device_token_id' => $deviceToken->id,
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            $notification->update([
                'delivery_status' => $anySent ? 'sent' : ($needsRetry ? 'pending' : 'failed'),
                'sent_at' => $anySent ? now() : null,
            ]);
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
    }
}
