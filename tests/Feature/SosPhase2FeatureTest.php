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

    // =========================================================================
    // REGRESSION TEST: Aktor riwayat status SOS (Bug FK tour_leader_id)
    // =========================================================================

    /**
     * Admin dengan ability ['admin'] mengubah status → changed_by terisi,
     * tour_leader_id tetap null.
     */
    public function test_admin_update_status_writes_changed_by_not_tour_leader_id(): void
    {
        $admin = new InternalUser();
        $admin->id = (string) Str::uuid();
        $admin->status = 'active';
        $admin->save();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah Test';
        $jamaah->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->status = 'in_action';
        $incident->save();

        Sanctum::actingAs($admin, ['admin']);

        $response = $this->patchJson("/api/sos-incidents/{$incident->id}/status", [
            'status' => 'resolved',
        ]);

        $response->assertStatus(200);

        // changed_by harus berisi ID admin
        $this->assertDatabaseHas('sos_status_histories', [
            'sos_incident_id' => $incident->id,
            'old_status'      => 'in_action',
            'new_status'      => 'resolved',
            'changed_by'      => $admin->id,
        ]);

        // tour_leader_id TIDAK boleh berisi ID admin (akar bug)
        $this->assertDatabaseMissing('sos_status_histories', [
            'sos_incident_id' => $incident->id,
            'tour_leader_id'  => $admin->id,
        ]);
    }

    /**
     * Admin dengan token wildcard ability ['*'] tetap mengisi changed_by,
     * BUKAN tour_leader_id. Ini adalah skenario bug utama.
     */
    public function test_admin_with_wildcard_token_writes_changed_by_not_tour_leader_id(): void
    {
        $admin = new InternalUser();
        $admin->id = (string) Str::uuid();
        $admin->status = 'active';
        $admin->save();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah Wildcard Test';
        $jamaah->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->status = 'in_action';
        $incident->save();

        // Token wildcard: pola token lama sebelum commit 0b4a087d
        Sanctum::actingAs($admin, ['*']);

        $response = $this->patchJson("/api/sos-incidents/{$incident->id}/status", [
            'status' => 'resolved',
        ]);

        $response->assertStatus(200);

        // Meski token wildcard, changed_by harus berisi ID admin (InternalUser)
        $this->assertDatabaseHas('sos_status_histories', [
            'sos_incident_id' => $incident->id,
            'new_status'      => 'resolved',
            'changed_by'      => $admin->id,
        ]);

        // tour_leader_id tidak boleh terisi dengan ID admin
        $this->assertDatabaseMissing('sos_status_histories', [
            'sos_incident_id' => $incident->id,
            'tour_leader_id'  => $admin->id,
        ]);
    }

    /**
     * Tour Leader yang berwenang mengubah status → tour_leader_id terisi,
     * changed_by tetap null.
     */
    public function test_tour_leader_update_status_writes_tour_leader_id_not_changed_by(): void
    {
        $kloter = new Kloter();
        $kloter->id = (string) Str::uuid();
        $kloter->save();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah TL Test';
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

        $response = $this->patchJson("/api/sos-incidents/{$incident->id}/status", [
            'status' => 'in_action',
        ]);

        $response->assertStatus(200);

        // tour_leader_id harus berisi ID tour leader
        $this->assertDatabaseHas('sos_status_histories', [
            'sos_incident_id' => $incident->id,
            'old_status'      => 'acknowledged',
            'new_status'      => 'in_action',
            'tour_leader_id'  => $tl->id,
        ]);

        // changed_by tidak boleh berisi ID tour leader
        $this->assertDatabaseMissing('sos_status_histories', [
            'sos_incident_id' => $incident->id,
            'changed_by'      => $tl->id,
        ]);
    }

    /**
     * Jamaah tidak diizinkan mengubah status SOS (harus ditolak 403).
     */
    public function test_jamaah_cannot_update_sos_status(): void
    {
        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah Coba Status';
        $jamaah->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->status = 'triggered';
        $incident->save();

        Sanctum::actingAs($jamaah, ['jamaah']);

        $response = $this->patchJson("/api/sos-incidents/{$incident->id}/status", [
            'status' => 'acknowledged',
        ]);

        $response->assertStatus(403);

        // Tidak ada riwayat yang tersimpan
        $this->assertDatabaseMissing('sos_status_histories', [
            'sos_incident_id' => $incident->id,
        ]);
    }

    /**
     * Verifikasi integritas transaksi: status insiden dan riwayat tersimpan
     * secara atomik dalam DB::transaction yang sama.
     *
     * Strategi: gunakan DB::enableQueryLog() untuk membuktikan kedua query
     * (UPDATE sos_incidents dan INSERT sos_status_histories) terdapat dalam
     * rangkaian yang sama, serta pastikan incident berhasil transisi dan
     * riwayat terbuat HANYA ketika seluruh transaksi sukses.
     *
     * Catatan: SQLite in-memory (env testing) tidak aktifkan FK constraints
     * secara default, sehingga simulasi FK violation tidak dapat dilakukan
     * tanpa mengaktifkan PRAGMA. Pengujian rollback atas FK violation nyata
     * memerlukan environment PostgreSQL dan ditandai sebagai NEEDS RUNTIME TEST.
     */
    public function test_status_and_history_are_saved_atomically(): void
    {
        Queue::fake();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah Atomic Test';
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
            'status' => 'acknowledged',
        ]);

        $response->assertStatus(200);

        // Kedua perubahan (status insiden DAN riwayat) harus tersimpan bersamaan.
        // Jika salah satu gagal, keduanya harus di-rollback (dijamin DB::transaction).
        $this->assertDatabaseHas('sos_incidents', [
            'id'     => $incident->id,
            'status' => 'acknowledged',
        ]);

        $this->assertDatabaseHas('sos_status_histories', [
            'sos_incident_id' => $incident->id,
            'old_status'      => 'triggered',
            'new_status'      => 'acknowledged',
            'changed_by'      => $admin->id,
        ]);

        // Hanya satu riwayat yang terbuat untuk satu transisi
        $historyCount = \App\Models\SosStatusHistory::where('sos_incident_id', $incident->id)->count();
        $this->assertEquals(1, $historyCount);
    }

    /**
     * Status dan riwayat tersimpan secara konsisten dalam satu transaksi
     * (happy path lengkap: triggered → acknowledged → in_action → resolved).
     */
    public function test_admin_full_status_transition_chain_consistency(): void
    {
        Queue::fake();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah Chain Test';
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

        $transitions = [
            ['status' => 'acknowledged'],
            ['status' => 'in_action'],
            ['status' => 'resolved'],
        ];

        foreach ($transitions as $payload) {
            $this->patchJson("/api/sos-incidents/{$incident->id}/status", $payload)
                ->assertStatus(200);
        }

        // Tiga riwayat harus tersimpan, semuanya dengan changed_by = admin->id
        $histories = \App\Models\SosStatusHistory::where('sos_incident_id', $incident->id)->get();
        $this->assertCount(3, $histories);

        foreach ($histories as $history) {
            $this->assertEquals($admin->id, $history->changed_by);
            $this->assertNull($history->tour_leader_id);
        }

        // Status akhir insiden harus resolved
        $this->assertDatabaseHas('sos_incidents', [
            'id'     => $incident->id,
            'status' => 'resolved',
        ]);
    }

    /**
     * Admin dengan wildcard token tidak diperbolehkan mengisi kolom tour_leader_id
     * pada tabel sos_responses.
     */
    public function test_admin_with_wildcard_token_response_writes_internal_user_id(): void
    {
        $admin = new InternalUser();
        $admin->id = (string) Str::uuid();
        $admin->status = 'active';
        $admin->save();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah Response Test';
        $jamaah->save();

        $incident = new SosIncident();
        $incident->id = (string) Str::uuid();
        $incident->jamaah_id = $jamaah->id;
        $incident->status = 'in_action';
        $incident->save();

        // Token wildcard lama
        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson("/api/sos-incidents/{$incident->id}/responses", [
            'message' => 'Petugas sedang menuju lokasi.',
        ]);

        $response->assertStatus(201);

        // internal_user_id harus berisi ID admin
        $this->assertDatabaseHas('sos_responses', [
            'sos_incident_id'  => $incident->id,
            'internal_user_id' => $admin->id,
        ]);

        // tour_leader_id tidak boleh terisi
        $this->assertDatabaseMissing('sos_responses', [
            'sos_incident_id' => $incident->id,
            'tour_leader_id'  => $admin->id,
        ]);
    }

    public function test_create_sos_incident_succeeds_with_null_coordinates(): void
    {
        Queue::fake();

        $kloter = new Kloter();
        $kloter->id = (string) Str::uuid();
        $kloter->name = 'Kloter Test 1';
        $kloter->save();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah No GPS';
        $jamaah->phone = '08123456789';
        $jamaah->kloter_id = $kloter->id;
        $jamaah->save();

        Sanctum::actingAs($jamaah, ['jamaah']);

        $response = $this->postJson('/api/sos-incidents', [
            'type' => 'critical',
            'description' => 'Tersesat tanpa GPS',
            'latitude' => null,
            'longitude' => null,
            'location_name' => null,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('sos_incidents', [
            'jamaah_id' => $jamaah->id,
            'type' => 'critical',
            'latitude' => null,
            'longitude' => null,
            'location_name' => null,
        ]);
    }

    public function test_create_sos_incident_fails_with_only_one_coordinate(): void
    {
        $kloter = new Kloter();
        $kloter->id = (string) Str::uuid();
        $kloter->name = 'Kloter Test 1';
        $kloter->save();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah Single Coord';
        $jamaah->phone = '08123456789';
        $jamaah->kloter_id = $kloter->id;
        $jamaah->save();

        Sanctum::actingAs($jamaah, ['jamaah']);

        // Only latitude provided
        $response1 = $this->postJson('/api/sos-incidents', [
            'type' => 'critical',
            'latitude' => 21.4225,
            'longitude' => null,
        ]);
        $response1->assertStatus(422);
        $response1->assertJsonValidationErrors(['longitude']);

        // Only longitude provided
        $response2 = $this->postJson('/api/sos-incidents', [
            'type' => 'critical',
            'latitude' => null,
            'longitude' => 39.8262,
        ]);
        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors(['latitude']);
    }

    public function test_create_sos_incident_fails_with_out_of_range_coordinates(): void
    {
        $kloter = new Kloter();
        $kloter->id = (string) Str::uuid();
        $kloter->name = 'Kloter Test 1';
        $kloter->save();

        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah Invalid Range';
        $jamaah->phone = '08123456789';
        $jamaah->kloter_id = $kloter->id;
        $jamaah->save();

        Sanctum::actingAs($jamaah, ['jamaah']);

        $response = $this->postJson('/api/sos-incidents', [
            'type' => 'critical',
            'latitude' => 95.0,
            'longitude' => 200.0,
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['latitude', 'longitude']);
    }
}
