<?php

namespace App\Jobs;

use App\Exceptions\InvalidFcmTokenException;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationDeviceDelivery;
use App\Models\SosIncident;
use App\Services\FcmService;
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
     *
     * @param  array<int, string>  $notificationIds
     */
    public function __construct(
        public SosIncident $incident,
        public array $notificationIds = [],
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(FcmService $fcm): void
    {
        if (empty($this->notificationIds)) {
            Log::warning('ProcessSosNotificationJob received empty notification IDs', [
                'incident_id' => $this->incident->id,
            ]);
            return;
        }

        $notifications = Notification::where('sos_incident_id', $this->incident->id)
            ->whereIn('id', $this->notificationIds)
            ->where('delivery_status', 'pending')
            ->get();

        Log::info('Processing SOS notifications', [
            'incident_id' => $this->incident->id,
            'notification_count' => $notifications->count(),
        ]);

        if ($notifications->isEmpty()) {
            Log::info('No pending notifications found to process', [
                'incident_id' => $this->incident->id,
                'notification_ids' => $this->notificationIds,
            ]);
            return;
        }

        $needsRetry = false;

        foreach ($notifications as $notification) {
            $result = $this->processNotification($notification, $fcm);

            if ($result === 'needs_retry') {
                $needsRetry = true;
            }
        }

        if ($needsRetry) {
            throw new \RuntimeException(
                'One or more SOS device deliveries need retry.'
            );
        }

        Log::info('SOS notifications processed successfully', [
            'incident_id' => $this->incident->id,
        ]);
    }

    /**
     * Process a single notification: send to all active device tokens.
     *
     * @return string 'sent'|'failed'|'needs_retry'
     */
    private function processNotification(
        Notification $notification,
        FcmService $fcm,
    ): string {
        $tokens = DeviceToken::where('tokenable_type', $notification->recipient_type)
            ->where('tokenable_id', $notification->recipient_id)
            ->active()
            ->get();

        if ($tokens->isEmpty()) {
            $notification->update([
                'delivery_status' => 'failed',
            ]);

            Log::warning('No active device tokens for notification', [
                'notification_id' => $notification->id,
                'recipient_type' => $notification->recipient_type,
                'recipient_id' => $notification->recipient_id,
            ]);

            return 'failed';
        }

        $payload = [
            'title' => $notification->title,
            'body'  => $notification->message,
            'data'  => [
                'notification_id' => $notification->id,
                'incident_id'     => (string) $notification->sos_incident_id,
                'status'          => (string) $this->incident->status,
                'type'            => (string) $notification->type,
            ],
        ];

        $hasFailedPermanent = false;
        $needsRetry = false;

        foreach ($tokens as $token) {
            $result = $this->sendToDevice($notification, $token, $payload, $fcm);

            if ($result === 'needs_retry') {
                $needsRetry = true;
            } elseif ($result === 'failed') {
                $hasFailedPermanent = true;
            }
        }

        if ($needsRetry) {
            // Keep notification as pending — the queue will retry.
            return 'needs_retry';
        }

        // Check final delivery statuses from the database.
        $deliveries = NotificationDeviceDelivery::where(
            'notification_id',
            $notification->id
        )->get();

        $allSent = $deliveries->isNotEmpty()
            && $deliveries->every(fn ($d) => $d->status === 'sent');

        if ($allSent) {
            $notification->update([
                'delivery_status' => 'sent',
                'sent_at'         => now(),
            ]);
        } else {
            $notification->update([
                'delivery_status' => 'failed',
            ]);
        }

        return $allSent ? 'sent' : 'failed';
    }

    /**
     * Send notification to a single device token.
     *
     * @return string 'sent'|'failed'|'needs_retry'
     */
    private function sendToDevice(
        Notification $notification,
        DeviceToken $token,
        array $payload,
        FcmService $fcm,
    ): string {
        $delivery = NotificationDeviceDelivery::firstOrCreate(
            [
                'notification_id' => $notification->id,
                'device_token_id' => $token->id,
            ],
            [
                'status'   => 'pending',
                'attempts' => 0,
            ]
        );

        // Skip deliveries that already succeeded (idempotency).
        if ($delivery->status === 'sent') {
            return 'sent';
        }

        $delivery->increment('attempts');

        try {
            $fcm->send($token->fcm_token, $payload);

            $delivery->update([
                'status'     => 'sent',
                'sent_at'    => now(),
                'last_error' => null,
            ]);

            $token->update(['last_used_at' => now()]);

            Log::info('FCM delivery succeeded', [
                'notification_id' => $notification->id,
                'device_token_id' => $token->id,
            ]);

            return 'sent';
        } catch (InvalidFcmTokenException $e) {
            $token->update(['is_active' => false]);

            $delivery->update([
                'status'     => 'failed',
                'last_error' => 'FCM token is unregistered.',
            ]);

            Log::warning('FCM token deactivated', [
                'notification_id' => $notification->id,
                'device_token_id' => $token->id,
            ]);

            return 'failed';
        } catch (\Throwable $e) {
            $delivery->update([
                'last_error' => $e->getMessage(),
            ]);

            Log::warning('FCM delivery failed (transient)', [
                'notification_id' => $notification->id,
                'device_token_id' => $token->id,
                'error'           => $e->getMessage(),
            ]);

            return 'needs_retry';
        }
    }

    /**
     * Handle a job failure after all queue retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('ProcessSosNotificationJob failed after retries', [
            'incident_id' => $this->incident->id,
            'exception'   => $exception->getMessage(),
        ]);

        // Mark all pending deliveries as failed.
        NotificationDeviceDelivery::whereIn(
            'notification_id',
            $this->notificationIds
        )
            ->where('status', '!=', 'sent')
            ->update([
                'status'     => 'failed',
                'last_error' => $exception->getMessage(),
            ]);

        // Mark all pending notifications as failed.
        Notification::whereIn('id', $this->notificationIds)
            ->where('delivery_status', 'pending')
            ->update([
                'delivery_status' => 'failed',
            ]);
    }
}