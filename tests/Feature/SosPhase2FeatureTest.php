<?php

namespace Tests\Feature;

use App\Jobs\ProcessSosNotificationJob;
use App\Models\DeviceToken;
use App\Models\InternalUser;
use App\Models\Jamaah;
use App\Models\Kloter;
use App\Models\Notification;
use App\Models\NotificationDeviceDelivery;
use App\Models\SosIncident;
use App\Models\SosResponse;
use App\Models\SosStatusHistory;
use App\Models\TourLeader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SosPhase2FeatureTest extends TestCase
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
            $table->string('phone')->nullable();
            $table->uuid('kloter_id')->nullable();
            $table->timestamps();
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

        Schema::create('sos_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('jamaah_id')->nullable();
            $table->uuid('kloter_id')->nullable();
            $table->string('type')->default('medical');
            $table->text('description')->nullable();
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('triggered');
            $table->timestamp('triggered_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sos_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sos_incident_id');
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);
            $table->string('reason', 500)->nullable();
            $table->uuid('changed_by')->nullable();
            $table->uuid('tour_leader_id')->nullable();
            $table->timestamp('changed_at')->useCurrent();
        });

        Schema::create('sos_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sos_incident_id');
            $table->uuid('jamaah_id')->nullable();
            $table->uuid('tour_leader_id')->nullable();
            $table->uuid('internal_user_id')->nullable();
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
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

        Schema::create('user_device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuidMorphs('tokenable');
            $table->string('fcm_token', 500)->unique();
            $table->string('platform')->default('android');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
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
        parent::tearDown();
    }

    private function dropTestTables(): void
    {
        Schema::dropIfExists('notification_device_deliveries');
        Schema::dropIfExists('user_device_tokens');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('sos_responses');
        Schema::dropIfExists('sos_status_histories');
        Schema::dropIfExists('sos_incidents');
        Schema::dropIfExists('kloter_leader_assignments');
        Schema::dropIfExists('tour_leaders');
        Schema::dropIfExists('internal_users');
        Schema::dropIfExists('jamaah');
        Schema::dropIfExists('kloters');
    }

    public function test_false_alarm_requires_reason(): void
    {
        $admin = new InternalUser();
        $admin->id = (string) Str::uuid();
        $admin->status = 'active';
        $admin->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->status = 'triggered';
        $incident->save();

        Sanctum::actingAs($admin, ['admin']);

        // Without reason should fail with 422
        $response = $this->patchJson("/api/sos-incidents/{$incident->id}/status", [
            'status' => 'false_alarm',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason']);
    }

    public function test_false_alarm_stores_reason_in_history_and_dispatches_notification(): void
    {
        Queue::fake([ProcessSosNotificationJob::class]);

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah Korban';
        $jamaah->save();

        $admin = new InternalUser();
        $admin->id = (string) Str::uuid();
        $admin->status = 'active';
        $admin->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->status = 'triggered';
        $incident->save();

        Sanctum::actingAs($admin, ['admin']);

        $response = $this->patchJson("/api/sos-incidents/{$incident->id}/status", [
            'status' => 'false_alarm',
            'reason' => 'Tombol panik kepencet di tas',
        ]);

        $response->assertStatus(200);

        // Assert history stored reason
        $this->assertDatabaseHas('sos_status_histories', [
            'sos_incident_id' => $incident->id,
            'new_status' => 'false_alarm',
            'reason' => 'Tombol panik kepencet di tas',
            'changed_by' => $admin->id,
        ]);

        // Assert notification created for jamaah
        $this->assertDatabaseHas('notifications', [
            'sos_incident_id' => $incident->id,
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah->id,
            'type' => 'sos_status_updated',
        ]);

        $notif = Notification::where('sos_incident_id', $incident->id)->firstOrFail();
        $this->assertStringContainsString('Tombol panik kepencet di tas', $notif->message);

        Queue::assertPushed(ProcessSosNotificationJob::class, function ($job) use ($incident, $notif) {
            return $job->incident->id === $incident->id
                && $job->notificationIds === [$notif->id];
        });
    }

    public function test_admin_response_creates_notification_for_jamaah_and_dispatches_job(): void
    {
        Queue::fake();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah SOS';
        $jamaah->save();

        $admin = new InternalUser();
        $admin->id = (string) Str::uuid();
        $admin->status = 'active';
        $admin->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->status = 'in_action';
        $incident->save();

        Sanctum::actingAs($admin, ['admin']);

        $response = $this->postJson("/api/sos-incidents/{$incident->id}/responses", [
            'message' => 'Petugas ambulans sudah mendekati Gate 18.',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('sos_responses', [
            'sos_incident_id' => $incident->id,
            'internal_user_id' => $admin->id,
            'message' => 'Petugas ambulans sudah mendekati Gate 18.',
        ]);

        $this->assertDatabaseHas('notifications', [
            'sos_incident_id' => $incident->id,
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah->id,
            'type' => 'sos_response_created',
        ]);

        $notif = Notification::where('sos_incident_id', $incident->id)->firstOrFail();

        Queue::assertPushed(ProcessSosNotificationJob::class, function ($job) use ($incident, $notif) {
            return $job->incident->id === $incident->id
                && $job->notificationIds === [$notif->id];
        });
    }

    public function test_tour_leader_response_creates_notification_for_jamaah(): void
    {
        Queue::fake();

        $kloter = new Kloter();
        $kloter->id = (string) Str::uuid();
        $kloter->save();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah Kloter';
        $jamaah->kloter_id = $kloter->id;
        $jamaah->save();

        $tl = new TourLeader();
        $tl->id = (string) Str::uuid();
        $tl->status = 'active';
        $tl->save();
        $tl->kloters()->attach($kloter->id, ['assigned_at' => now()]);

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->kloter_id = $kloter->id;
        $incident->status = 'acknowledged';
        $incident->save();

        Sanctum::actingAs($tl, ['tour_leader']);

        $response = $this->postJson("/api/sos-incidents/{$incident->id}/responses", [
            'message' => 'Saya sedang menuju ke posisi Anda sekarang.',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'sos_incident_id' => $incident->id,
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah->id,
            'type' => 'sos_response_created',
        ]);

        Queue::assertPushed(ProcessSosNotificationJob::class);
    }

    public function test_jamaah_response_does_not_create_notification_for_jamaah(): void
    {
        Queue::fake();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah Sendiri';
        $jamaah->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->status = 'in_action';
        $incident->save();

        Sanctum::actingAs($jamaah, ['jamaah']);

        $response = $this->postJson("/api/sos-incidents/{$incident->id}/responses", [
            'message' => 'Saya berada di dekat tiang nomor 5.',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseMissing('notifications', [
            'sos_incident_id' => $incident->id,
            'recipient_type' => Jamaah::class,
            'type' => 'sos_response_created',
        ]);

        Queue::assertNotPushed(ProcessSosNotificationJob::class);
    }

    public function test_read_routes_accessible_and_separated_from_write(): void
    {
        $admin = new InternalUser();
        $admin->id = (string) Str::uuid();
        $admin->status = 'active';
        $admin->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->status = 'triggered';
        $incident->save();

        Sanctum::actingAs($admin, ['admin']);

        // Read routes
        $this->getJson('/api/sos-incidents')->assertStatus(200);
        $this->getJson("/api/sos-incidents/{$incident->id}")->assertStatus(200);
        $this->getJson("/api/sos-incidents/{$incident->id}/responses")->assertStatus(200);
    }
}
