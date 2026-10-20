<?php

namespace Tests\Feature;

use App\Events\SosCreated;
use App\Events\SosStatusUpdated;
use App\Jobs\ProcessSosNotificationJob;
use App\Listeners\SendSosCreatedNotification;
use App\Listeners\SendSosStatusUpdatedNotification;
use App\Models\DeviceToken;
use App\Models\InternalUser;
use App\Models\Jamaah;
use App\Models\Kloter;
use App\Models\Notification;
use App\Models\NotificationDeviceDelivery;
use App\Models\SosIncident;
use App\Models\TourLeader;
use App\Services\FcmService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class SosNotificationListenersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->dropTestTables();

        Schema::create('kloters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('jamaah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name')->nullable();
            $table->uuid('kloter_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sos_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('jamaah_id')->nullable();
            $table->uuid('kloter_id')->nullable();
            $table->string('location_name')->nullable();
            $table->string('status')->default('triggered');
        });

        Schema::create('internal_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('tour_leaders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('kloter_leader_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('tour_leader_id');
            $table->uuid('kloter_id');
            $table->timestamp('assigned_at')->nullable();
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

        Schema::create('notification_device_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignUuid('device_token_id')->constrained('user_device_tokens')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(
                ['notification_id', 'device_token_id'],
                'notification_device_deliveries_unique'
            );
        });
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
        Schema::dropIfExists('kloter_leader_assignments');
        Schema::dropIfExists('tour_leaders');
        Schema::dropIfExists('internal_users');
        Schema::dropIfExists('sos_incidents');
        Schema::dropIfExists('jamaah');
        Schema::dropIfExists('kloters');
    }

    public function test_send_sos_created_notification_dispatches_job_with_created_notification_ids(): void
    {
        Queue::fake();

        $kloter = new Kloter();
        $kloter->id = (string) Str::uuid();
        $kloter->save();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Ahmad Jamaah';
        $jamaah->kloter_id = $kloter->id;
        $jamaah->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->kloter_id = $kloter->id;
        $incident->location_name = 'Masjidil Haram';
        $incident->save();

        // 1 Active admin
        $admin = new InternalUser();
        $admin->id = (string) Str::uuid();
        $admin->full_name = 'Admin Pusat';
        $admin->status = 'active';
        $admin->save();

        // 1 Tour Leader assigned to this kloter
        $tl = new TourLeader();
        $tl->id = (string) Str::uuid();
        $tl->full_name = 'Ustadz Mutawif';
        $tl->status = 'active';
        $tl->save();
        $tl->kloters()->attach($kloter->id, ['assigned_at' => now()]);

        $listener = new SendSosCreatedNotification();
        $listener->handle(new SosCreated($incident, $jamaah));

        $createdNotifications = Notification::where('sos_incident_id', $incident->id)->get();
        $this->assertCount(2, $createdNotifications);

        $expectedIds = $createdNotifications->pluck('id')->all();

        Queue::assertPushed(ProcessSosNotificationJob::class, function ($job) use ($incident, $expectedIds) {
            return $job->incident->id === $incident->id
                && count($job->notificationIds) === 2
                && empty(array_diff($expectedIds, $job->notificationIds));
        });
    }

    public function test_send_sos_created_notification_excludes_inactive_tour_leader_and_other_kloter(): void
    {
        Queue::fake();

        $kloter1 = new Kloter();
        $kloter1->id = (string) Str::uuid();
        $kloter1->save();

        $kloter2 = new Kloter();
        $kloter2->id = (string) Str::uuid();
        $kloter2->save();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah Kloter 1';
        $jamaah->kloter_id = $kloter1->id;
        $jamaah->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->kloter_id = $kloter1->id;
        $incident->location_name = 'Mina';
        $incident->save();

        // Active admin
        $admin = new InternalUser();
        $admin->id = (string) Str::uuid();
        $admin->full_name = 'Admin';
        $admin->status = 'active';
        $admin->save();

        // Inactive TL in same kloter (status = inactive)
        $tlInactive = new TourLeader();
        $tlInactive->id = (string) Str::uuid();
        $tlInactive->full_name = 'TL Inactive';
        $tlInactive->status = 'inactive';
        $tlInactive->save();
        $tlInactive->kloters()->attach($kloter1->id, ['assigned_at' => now()]);

        // Active TL in DIFFERENT kloter
        $tlOtherKloter = new TourLeader();
        $tlOtherKloter->id = (string) Str::uuid();
        $tlOtherKloter->full_name = 'TL Kloter 2';
        $tlOtherKloter->status = 'active';
        $tlOtherKloter->save();
        $tlOtherKloter->kloters()->attach($kloter2->id, ['assigned_at' => now()]);

        $listener = new SendSosCreatedNotification();
        $listener->handle(new SosCreated($incident, $jamaah));

        // Only Admin should receive notification
        $createdNotifications = Notification::where('sos_incident_id', $incident->id)->get();
        $this->assertCount(1, $createdNotifications);
        $this->assertSame(InternalUser::class, $createdNotifications->first()->recipient_type);
        $this->assertSame($admin->id, $createdNotifications->first()->recipient_id);

        $this->assertDatabaseMissing('notifications', [
            'sos_incident_id' => $incident->id,
            'recipient_type' => TourLeader::class,
            'recipient_id' => $tlInactive->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'sos_incident_id' => $incident->id,
            'recipient_type' => TourLeader::class,
            'recipient_id' => $tlOtherKloter->id,
        ]);
    }

    public function test_send_sos_created_notification_skips_dispatch_when_no_recipients_exist(): void
    {
        Queue::fake();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->save();

        // No admins, no tour leaders exist
        $listener = new SendSosCreatedNotification();
        $listener->handle(new SosCreated($incident, null));

        Queue::assertNotPushed(ProcessSosNotificationJob::class);
    }

    public function test_send_sos_status_updated_notification_dispatches_job_with_single_notification_id(): void
    {
        Queue::fake();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Budi Jamaah';
        $jamaah->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->status = 'acknowledged';
        $incident->save();

        $listener = new SendSosStatusUpdatedNotification();
        $listener->handle(new SosStatusUpdated($incident, 'triggered', 'acknowledged', null));

        $createdNotifications = Notification::where('sos_incident_id', $incident->id)->get();
        $this->assertCount(1, $createdNotifications);

        $notifId = $createdNotifications->first()->id;

        Queue::assertPushed(ProcessSosNotificationJob::class, function ($job) use ($incident, $notifId) {
            return $job->incident->id === $incident->id
                && $job->notificationIds === [$notifId];
        });
    }

    public function test_send_sos_status_updated_notification_handles_false_alarm_with_reason(): void
    {
        Queue::fake();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Siti Jamaah';
        $jamaah->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->status = 'false_alarm';
        $incident->save();

        $listener = new SendSosStatusUpdatedNotification();
        $reason = 'Tombol tertekan saat di saku baju';
        $listener->handle(new SosStatusUpdated($incident, 'triggered', 'false_alarm', null, $reason));

        $createdNotification = Notification::where('sos_incident_id', $incident->id)->firstOrFail();
        $this->assertSame(Jamaah::class, $createdNotification->recipient_type);
        $this->assertSame($jamaah->id, $createdNotification->recipient_id);
        $this->assertStringContainsString($reason, $createdNotification->message);

        Queue::assertPushed(ProcessSosNotificationJob::class, function ($job) use ($incident, $createdNotification) {
            return $job->incident->id === $incident->id
                && $job->notificationIds === [$createdNotification->id];
        });
    }

    public function test_send_sos_status_updated_notification_for_in_action_and_resolved(): void
    {
        Queue::fake();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->save();

        $listener = new SendSosStatusUpdatedNotification();

        // in_action
        $incident->status = 'in_action';
        $incident->save();
        $listener->handle(new SosStatusUpdated($incident, 'acknowledged', 'in_action', null));

        // resolved
        $incident->status = 'resolved';
        $incident->save();
        $listener->handle(new SosStatusUpdated($incident, 'in_action', 'resolved', null));

        $notifications = Notification::where('sos_incident_id', $incident->id)->get();
        $this->assertCount(2, $notifications);

        Queue::assertPushed(ProcessSosNotificationJob::class, 2);
    }

    public function test_send_sos_status_updated_notification_skips_untracked_status(): void
    {
        Queue::fake();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->save();

        $listener = new SendSosStatusUpdatedNotification();
        $listener->handle(new SosStatusUpdated($incident, 'triggered', 'some_unknown_status', null));

        Queue::assertNotPushed(ProcessSosNotificationJob::class);
        $this->assertDatabaseMissing('notifications', ['sos_incident_id' => $incident->id]);
    }

    public function test_end_to_end_listener_dispatch_to_job_execution(): void
    {
        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'E2E Jamaah';
        $jamaah->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->status = 'acknowledged';
        $incident->save();

        // Create active device token for jamaah
        $token = new DeviceToken();
        $token->id = (string) Str::uuid();
        $token->tokenable_type = Jamaah::class;
        $token->tokenable_id = $jamaah->id;
        $token->fcm_token = 'fcm-token-' . Str::random(24);
        $token->platform = 'android';
        $token->is_active = true;
        $token->save();

        $notification = null;

        // Mock FCM in container so sync queue dispatches job using this mock
        $fcm = Mockery::mock(FcmService::class);
        $this->app->instance(FcmService::class, $fcm);

        $fcm->shouldReceive('send')
            ->once()
            ->withArgs(function ($deviceToken, $payload) use ($token, $incident) {
                return $deviceToken === $token->fcm_token
                    && $payload['title'] === 'Status Laporan: acknowledged'
                    && !empty($payload['data']['notification_id'])
                    && $payload['data']['incident_id'] === $incident->id;
            })
            ->andReturn(['name' => 'fcm-message-id-e2e']);

        // Run listener directly - sync queue immediately executes ProcessSosNotificationJob
        $listener = new SendSosStatusUpdatedNotification();
        $listener->handle(new SosStatusUpdated($incident, 'triggered', 'acknowledged', null));

        $notification = Notification::where('sos_incident_id', $incident->id)->firstOrFail();

        $notification->refresh();
        $this->assertSame('sent', $notification->delivery_status);
        $this->assertNotNull($notification->sent_at);

        $delivery = NotificationDeviceDelivery::where('notification_id', $notification->id)->firstOrFail();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
    }
}
