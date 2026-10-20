<?php

namespace Tests\Feature;

use App\Exceptions\InvalidFcmTokenException;
use App\Jobs\ProcessSosNotificationJob;
use App\Models\DeviceToken;
use App\Models\InternalUser;
use App\Models\Notification;
use App\Models\NotificationDeviceDelivery;
use App\Models\SosIncident;
use App\Services\FcmService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessSosNotificationJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTestTables();

        Schema::create('sos_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
        });

        Schema::create('internal_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name')->nullable();
            $table->timestamps();
        });

        Schema::create('user_device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('tokenable');
            $table->string('fcm_token', 500)->unique();
            $table->string('platform')->default('android');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sos_incident_id')->nullable();
            $table->string('recipient_type');
            $table->string('recipient_id');
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('delivery_status')->default('pending');
            $table->timestamps();
        });

        Schema::create(
            'notification_device_deliveries',
            function (Blueprint $table) {
                $table->uuid('id')->primary();

                $table->foreignUuid('notification_id')
                    ->constrained('notifications')
                    ->cascadeOnDelete();

                $table->foreignUuid('device_token_id')
                    ->constrained('user_device_tokens')
                    ->cascadeOnDelete();

                $table->string('status')->default('pending');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('sent_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->unique(
                    ['notification_id', 'device_token_id'],
                    'notification_device_deliveries_unique'
                );

                $table->index(['status', 'updated_at']);
            }
        );
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();

        Mockery::close();

        parent::tearDown();
    }

    private function dropTestTables(): void
    {
        Schema::dropIfExists('notification_device_deliveries');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('user_device_tokens');
        Schema::dropIfExists('internal_users');
        Schema::dropIfExists('sos_incidents');
    }

    private function createNotificationWithRecipient(): array
    {
        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->save();

        $recipient = new InternalUser();
        $recipient->id = (string) Str::uuid();
        $recipient->full_name = 'Test Admin';
        $recipient->save();

        $notification = new Notification();
        $notification->id = (string) Str::uuid();
        $notification->sos_incident_id = $incident->id;
        $notification->recipient_type = InternalUser::class;
        $notification->recipient_id = $recipient->id;
        $notification->type = 'sos_created';
        $notification->title = 'SOS Darurat';
        $notification->message = 'Jamaah membutuhkan bantuan';
        $notification->delivery_status = 'pending';
        $notification->save();

        return [$incident, $recipient, $notification];
    }

    private function createDeviceToken(
        InternalUser $recipient
    ): DeviceToken {
        $token = new DeviceToken();
        $token->id = (string) Str::uuid();
        $token->tokenable_type = InternalUser::class;
        $token->tokenable_id = $recipient->id;
        $token->fcm_token = 'test-fcm-token-' . Str::random(24);
        $token->platform = 'android';
        $token->is_active = true;
        $token->save();

        return $token;
    }

    private function createJob(
        SosIncident $incident,
        Notification $notification
    ): ProcessSosNotificationJob {
        return new ProcessSosNotificationJob(
            $incident,
            [$notification->id]
        );
    }

    public function test_successful_fcm_delivery_marks_notification_sent(): void
    {
        [$incident, $recipient, $notification] =
            $this->createNotificationWithRecipient();

        $token = $this->createDeviceToken($recipient);

        $fcm = Mockery::mock(FcmService::class);

        $fcm->shouldReceive('send')
            ->once()
            ->withArgs(
                fn ($deviceToken, $data) =>
                    $deviceToken === $token->fcm_token
                    && $data['title'] === 'SOS Darurat'
                    && $data['body'] === 'Jamaah membutuhkan bantuan'
                    && $data['data']['notification_id'] === $notification->id
                    && $data['data']['incident_id'] === $incident->id
            )
            ->andReturn(['name' => 'test-message-id']);

        $this->createJob($incident, $notification)->handle($fcm);

        $notification->refresh();
        $token->refresh();

        $delivery = NotificationDeviceDelivery::query()
            ->where('notification_id', $notification->id)
            ->where('device_token_id', $token->id)
            ->firstOrFail();

        $this->assertSame('sent', $notification->delivery_status);
        $this->assertNotNull($notification->sent_at);

        $this->assertSame('sent', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->sent_at);
        $this->assertNull($delivery->last_error);

        $this->assertNotNull($token->last_used_at);
    }

    public function test_missing_active_device_token_marks_notification_failed(): void
    {
        [$incident, , $notification] =
            $this->createNotificationWithRecipient();

        $fcm = Mockery::mock(FcmService::class);
        $fcm->shouldNotReceive('send');

        $this->createJob($incident, $notification)->handle($fcm);

        $notification->refresh();

        $this->assertSame('failed', $notification->delivery_status);
        $this->assertNull($notification->sent_at);

        $this->assertDatabaseMissing(
            'notification_device_deliveries',
            ['notification_id' => $notification->id]
        );
    }

    public function test_transient_fcm_error_keeps_delivery_pending_for_retry(): void
    {
        [$incident, $recipient, $notification] =
            $this->createNotificationWithRecipient();

        $token = $this->createDeviceToken($recipient);

        $fcm = Mockery::mock(FcmService::class);

        $fcm->shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Simulated FCM failure'));

        $job = $this->createJob($incident, $notification);

        try {
            $job->handle($fcm);
            $this->fail('Expected a retryable FCM exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'One or more SOS device deliveries need retry.',
                $exception->getMessage()
            );
        }

        $notification->refresh();

        $delivery = NotificationDeviceDelivery::query()
            ->where('notification_id', $notification->id)
            ->where('device_token_id', $token->id)
            ->firstOrFail();

        $this->assertSame('pending', $notification->delivery_status);
        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(
            'Simulated FCM failure',
            $delivery->last_error
        );
        $this->assertNull($notification->sent_at);
    }

    public function test_unregistered_fcm_token_is_deactivated(): void
    {
        [$incident, $recipient, $notification] =
            $this->createNotificationWithRecipient();

        $token = $this->createDeviceToken($recipient);

        $fcm = Mockery::mock(FcmService::class);

        $fcm->shouldReceive('send')
            ->once()
            ->andThrow(
                new InvalidFcmTokenException(
                    'Firebase registration token is unregistered.'
                )
            );

        $this->createJob($incident, $notification)->handle($fcm);

        $token->refresh();
        $notification->refresh();

        $delivery = NotificationDeviceDelivery::query()
            ->where('notification_id', $notification->id)
            ->where('device_token_id', $token->id)
            ->firstOrFail();

        $this->assertFalse((bool) $token->is_active);
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(
            'FCM token is unregistered.',
            $delivery->last_error
        );

        $this->assertSame('failed', $notification->delivery_status);
        $this->assertNull($notification->sent_at);
    }

    public function test_multiple_active_devices_are_processed_successfully(): void
    {
        [$incident, $recipient, $notification] =
            $this->createNotificationWithRecipient();

        $token1 = $this->createDeviceToken($recipient);
        $token2 = $this->createDeviceToken($recipient);

        $deviceTokens = [$token1->fcm_token, $token2->fcm_token];

        $fcm = Mockery::mock(FcmService::class);

        $fcm->shouldReceive('send')
            ->twice()
            ->withArgs(
                fn ($deviceToken, $data) =>
                    in_array($deviceToken, $deviceTokens, true)
                    && $data['title'] === 'SOS Darurat'
            )
            ->andReturn(['name' => 'test-message-id']);

        $this->createJob($incident, $notification)->handle($fcm);

        $notification->refresh();
        $token1->refresh();
        $token2->refresh();

        $deliveries = NotificationDeviceDelivery::query()
            ->where('notification_id', $notification->id)
            ->get();

        $this->assertSame('sent', $notification->delivery_status);
        $this->assertNotNull($notification->sent_at);

        $this->assertCount(2, $deliveries);
        $this->assertSame(
            2,
            $deliveries->where('status', 'sent')->count()
        );

        $this->assertNotNull($token1->last_used_at);
        $this->assertNotNull($token2->last_used_at);
    }

    public function test_successful_device_is_not_resent_when_another_device_retries(): void
    {
        [$incident, $recipient, $notification] =
            $this->createNotificationWithRecipient();

        $token1 = $this->createDeviceToken($recipient);
        $token2 = $this->createDeviceToken($recipient);

        $job = $this->createJob($incident, $notification);

        // Attempt 1: device 1 succeeds, device 2 fails temporarily.
        $firstFcm = Mockery::mock(FcmService::class);

        $firstFcm->shouldReceive('send')
            ->once()
            ->withArgs(
                fn ($deviceToken, $data) =>
                    $deviceToken === $token1->fcm_token
            )
            ->andReturn(['name' => 'test-message-1']);

        $firstFcm->shouldReceive('send')
            ->once()
            ->withArgs(
                fn ($deviceToken, $data) =>
                    $deviceToken === $token2->fcm_token
            )
            ->andThrow(new RuntimeException('Temporary device failure'));

        try {
            $job->handle($firstFcm);
            $this->fail('Expected a retryable FCM exception.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'One or more SOS device deliveries need retry.',
                $exception->getMessage()
            );
        }

        $delivery1 = NotificationDeviceDelivery::query()
            ->where('notification_id', $notification->id)
            ->where('device_token_id', $token1->id)
            ->firstOrFail();

        $delivery2 = NotificationDeviceDelivery::query()
            ->where('notification_id', $notification->id)
            ->where('device_token_id', $token2->id)
            ->firstOrFail();

        $this->assertSame('sent', $delivery1->status);
        $this->assertSame('pending', $delivery2->status);

        // Attempt 2: only device 2 should be retried.
        $retryFcm = Mockery::mock(FcmService::class);

        $retryFcm->shouldNotReceive('send')
            ->with($token1->fcm_token, Mockery::any());

        $retryFcm->shouldReceive('send')
            ->once()
            ->withArgs(
                fn ($deviceToken, $data) =>
                    $deviceToken === $token2->fcm_token
            )
            ->andReturn(['name' => 'test-message-2']);

        $job->handle($retryFcm);

        $notification->refresh();

        $delivery1->refresh();
        $delivery2->refresh();

        $this->assertSame('sent', $notification->delivery_status);
        $this->assertNotNull($notification->sent_at);

        $this->assertSame('sent', $delivery1->status);
        $this->assertSame(1, $delivery1->attempts);

        $this->assertSame('sent', $delivery2->status);
        $this->assertSame(2, $delivery2->attempts);
        $this->assertNotNull($delivery2->sent_at);
        $this->assertNull($delivery2->last_error);
    }

    public function test_all_failed_deliveries_become_failed_after_queue_exhaustion(): void
    {
        [$incident, $recipient, $notification] =
            $this->createNotificationWithRecipient();

        $token1 = $this->createDeviceToken($recipient);
        $token2 = $this->createDeviceToken($recipient);

        $job = $this->createJob($incident, $notification);

        // Simulate three failed queue attempts.
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $fcm = Mockery::mock(FcmService::class);

            $fcm->shouldReceive('send')
                ->twice()
                ->andThrow(
                    new RuntimeException(
                        "Simulated failure on attempt {$attempt}"
                    )
                );

            try {
                $job->handle($fcm);
                $this->fail('Expected a retryable FCM exception.');
            } catch (RuntimeException $exception) {
                $this->assertSame(
                    'One or more SOS device deliveries need retry.',
                    $exception->getMessage()
                );
            }
        }

        // Laravel calls failed() when all queue attempts are exhausted.
        $job->failed(
            new RuntimeException('Maximum queue attempts exceeded.')
        );

        $notification->refresh();

        $delivery1 = NotificationDeviceDelivery::query()
            ->where('notification_id', $notification->id)
            ->where('device_token_id', $token1->id)
            ->firstOrFail();

        $delivery2 = NotificationDeviceDelivery::query()
            ->where('notification_id', $notification->id)
            ->where('device_token_id', $token2->id)
            ->firstOrFail();

        $this->assertSame('failed', $notification->delivery_status);
        $this->assertNull($notification->sent_at);

        $this->assertSame('failed', $delivery1->status);
        $this->assertSame(3, $delivery1->attempts);
        $this->assertSame(
            'Maximum queue attempts exceeded.',
            $delivery1->last_error
        );

        $this->assertSame('failed', $delivery2->status);
        $this->assertSame(3, $delivery2->attempts);
        $this->assertSame(
            'Maximum queue attempts exceeded.',
            $delivery2->last_error
        );
    }
}
