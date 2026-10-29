<?php

namespace Tests\Feature;

use App\Models\Broadcast;
use App\Models\BroadcastRecipientKloter;
use App\Models\FamilyRelation;
use App\Models\InternalUser;
use App\Models\Jamaah;
use App\Models\Kloter;
use App\Models\Notification;
use App\Models\TourLeader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BroadcastFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->dropTestTables();

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

        Schema::create('kloters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 30)->unique();
            $table->string('name', 150)->nullable();
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamps();
        });

        Schema::create('kloter_leader_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('kloter_id');
            $table->uuid('tour_leader_id');
            $table->timestamp('assigned_at')->useCurrent();
        });

        Schema::create('jamaah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('login_id', 50)->nullable();
            $table->uuid('kloter_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('family_relations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('jamaah_id');
            $table->string('name', 150)->nullable();
            $table->string('relation_type', 50)->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title', 150)->default('Pengumuman');
            $table->string('sender_type');
            $table->uuid('sender_id');
            $table->text('message');
            $table->string('category', 50)->default('general');
            $table->string('priority', 20)->default('normal');
            $table->string('target_type', 30)->default('all');
            $table->string('status', 30)->default('published');
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('broadcast_recipient_kloters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('broadcast_id');
            $table->uuid('kloter_id');
        });

        Schema::create('broadcast_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('broadcast_id');
            $table->string('recipient_type');
            $table->uuid('recipient_id');
            $table->string('status')->default('sent');
            $table->timestamp('read_at')->nullable();
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
            $table->uuid('broadcast_id')->nullable();
            $table->uuid('sos_incident_id')->nullable();
            $table->string('recipient_type');
            $table->uuid('recipient_id');
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('delivery_status')->default('pending');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        parent::tearDown();
    }

    private function dropTestTables(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('broadcast_logs');
        Schema::dropIfExists('broadcast_recipient_kloters');
        Schema::dropIfExists('broadcasts');
        Schema::dropIfExists('family_relations');
        Schema::dropIfExists('jamaah');
        Schema::dropIfExists('kloter_leader_assignments');
        Schema::dropIfExists('kloters');
        Schema::dropIfExists('tour_leaders');
        Schema::dropIfExists('internal_users');
        Schema::dropIfExists('personal_access_tokens');
    }

    public function test_admin_can_publish_global_broadcast_to_active_journey_jamaah(): void
    {
        Queue::fake();

        $admin = InternalUser::create(['id' => (string) Str::uuid(), 'full_name' => 'Admin Utama']);
        Sanctum::actingAs($admin, ['admin']);

        // Kloter 1 Active, Kloter 2 Draft
        $activeKloter = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLT-ACTIVE', 'status' => 'active']);
        $draftKloter = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLT-DRAFT', 'status' => 'draft']);

        // Jamaah 1 on active kloter, Jamaah 2 on draft kloter
        $activeJamaah = Jamaah::create(['id' => (string) Str::uuid(), 'kloter_id' => $activeKloter->id, 'status' => 'active']);
        $draftJamaah = Jamaah::create(['id' => (string) Str::uuid(), 'kloter_id' => $draftKloter->id, 'status' => 'active']);

        $res = $this->postJson('/api/broadcasts', [
            'title' => 'Pengumuman Penting Makkah',
            'message' => 'Harap berkumpul di lobi hotel.',
            'category' => 'ibadah',
            'priority' => 'urgent',
            'target_type' => 'all',
        ]);

        $res->assertCreated();
        $broadcastId = $res->json('data.broadcast.id');

        // Assert broadcast record created
        $this->assertDatabaseHas('broadcasts', [
            'id' => $broadcastId,
            'title' => 'Pengumuman Penting Makkah',
            'status' => 'published',
        ]);

        // Assert only active journey jamaah received notification
        $this->assertDatabaseHas('notifications', [
            'broadcast_id' => $broadcastId,
            'recipient_id' => $activeJamaah->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'broadcast_id' => $broadcastId,
            'recipient_id' => $draftJamaah->id,
        ]);
    }

    public function test_tour_leader_can_publish_broadcast_to_assigned_kloter(): void
    {
        Queue::fake();

        $tl = TourLeader::create(['id' => (string) Str::uuid(), 'full_name' => 'TL Ust. Zaki']);
        $kloter = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLT-01', 'status' => 'active']);
        $tl->kloters()->attach($kloter->id, ['id' => (string) Str::uuid()]);

        $jamaah = Jamaah::create(['id' => (string) Str::uuid(), 'kloter_id' => $kloter->id, 'status' => 'active']);

        Sanctum::actingAs($tl, ['tour_leader']);

        $res = $this->postJson('/api/broadcasts', [
            'title' => 'Jadwal Raudhah',
            'message' => 'Kumpul jam 07.00 di lobi.',
            'target_type' => 'kloter',
            'kloter_ids' => [$kloter->id],
        ]);

        $res->assertCreated();
        $broadcastId = $res->json('data.broadcast.id');

        $this->assertDatabaseHas('notifications', [
            'broadcast_id' => $broadcastId,
            'recipient_id' => $jamaah->id,
        ]);
    }

    public function test_tour_leader_is_forbidden_when_targeting_unassigned_kloter(): void
    {
        Queue::fake();

        $tl = TourLeader::create(['id' => (string) Str::uuid(), 'full_name' => 'TL Ust. Zaki']);
        $myKloter = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLT-01', 'status' => 'active']);
        $otherKloter = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLT-02', 'status' => 'active']);

        $tl->kloters()->attach($myKloter->id, ['id' => (string) Str::uuid()]);

        Sanctum::actingAs($tl, ['tour_leader']);

        // Try to broadcast to other kloter
        $res = $this->postJson('/api/broadcasts', [
            'title' => 'Hacked Broadcast',
            'message' => 'Unauthorized message',
            'target_type' => 'kloter',
            'kloter_ids' => [$otherKloter->id],
        ]);

        $res->assertStatus(403);
        $this->assertDatabaseEmpty('broadcasts');
        $this->assertDatabaseEmpty('notifications');
    }

    public function test_tour_leader_cannot_publish_global_broadcast(): void
    {
        Queue::fake();

        $tl = TourLeader::create(['id' => (string) Str::uuid(), 'full_name' => 'TL Ust. Zaki']);
        Sanctum::actingAs($tl, ['tour_leader']);

        $res = $this->postJson('/api/broadcasts', [
            'title' => 'Global Message by TL',
            'message' => 'Should fail',
            'target_type' => 'all',
        ]);

        $res->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/broadcasts', [
            'message' => 'No auth',
            'target_type' => 'all',
        ])->assertUnauthorized();

        $this->getJson('/api/broadcasts')->assertUnauthorized();
    }

    public function test_idempotency_prevents_duplicate_broadcast_and_notifications(): void
    {
        Queue::fake();

        $admin = InternalUser::create(['id' => (string) Str::uuid(), 'full_name' => 'Admin']);
        Sanctum::actingAs($admin, ['admin']);

        $kloter = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLT-IDEM', 'status' => 'active']);
        $jamaah = Jamaah::create(['id' => (string) Str::uuid(), 'kloter_id' => $kloter->id, 'status' => 'active']);

        $idemKey = (string) Str::uuid();

        // 1st submit
        $res1 = $this->withHeader('X-Idempotency-Key', $idemKey)
            ->postJson('/api/broadcasts', [
                'title' => 'Test Idempotency',
                'message' => 'Message 1',
                'target_type' => 'kloter',
                'kloter_ids' => [$kloter->id],
            ]);
        $res1->assertCreated();

        // 2nd submit with SAME key & payload
        $res2 = $this->withHeader('X-Idempotency-Key', $idemKey)
            ->postJson('/api/broadcasts', [
                'title' => 'Test Idempotency',
                'message' => 'Message 1',
                'target_type' => 'kloter',
                'kloter_ids' => [$kloter->id],
            ]);
        $res2->assertOk(); // Replay 200

        // Ensure records count remains exactly 1
        $this->assertCount(1, Broadcast::all());
        $this->assertCount(1, Notification::all());

        // 3rd submit with SAME key but DIFFERENT payload -> Expect 409 Conflict
        $res3 = $this->withHeader('X-Idempotency-Key', $idemKey)
            ->postJson('/api/broadcasts', [
                'title' => 'Conflict Title',
                'message' => 'Different Message',
                'target_type' => 'kloter',
                'kloter_ids' => [$kloter->id],
            ]);
        $res3->assertStatus(409);
    }

    public function test_broadcast_show_and_update_with_audit_trail(): void
    {
        $admin = InternalUser::create(['id' => (string) Str::uuid(), 'full_name' => 'Admin']);
        Sanctum::actingAs($admin, ['admin']);

        $broadcast = Broadcast::create([
            'id' => (string) Str::uuid(),
            'title' => 'Original Title',
            'sender_type' => InternalUser::class,
            'sender_id' => $admin->id,
            'message' => 'Original Message',
            'target_type' => 'all',
            'status' => 'published',
            'sent_at' => now(),
        ]);

        // Show detail
        $showRes = $this->getJson("/api/broadcasts/{$broadcast->id}");
        $showRes->assertOk();
        $showRes->assertJsonPath('data.broadcast.id', $broadcast->id);

        // Update title and message
        $patchRes = $this->patchJson("/api/broadcasts/{$broadcast->id}", [
            'title' => 'Updated Title',
            'message' => 'Updated Message',
        ]);
        $patchRes->assertOk();
        $this->assertEquals('Updated Title', $broadcast->fresh()->title);
        $this->assertEquals('Updated Message', $broadcast->fresh()->message);
    }

    public function test_sender_morph_relation_can_be_resolved_for_both_admin_and_tour_leader(): void
    {
        $admin = InternalUser::create(['id' => (string) Str::uuid(), 'full_name' => 'Admin Utama']);
        $adminBroadcast = Broadcast::create([
            'id' => (string) Str::uuid(),
            'title' => 'Admin Broadcast',
            'sender_type' => InternalUser::class,
            'sender_id' => $admin->id,
            'message' => 'Pesan dari admin',
            'target_type' => 'all',
            'status' => 'published',
            'sent_at' => now(),
        ]);

        $tl = TourLeader::create(['id' => (string) Str::uuid(), 'full_name' => 'Ust. Zaki TL']);
        $tlBroadcast = Broadcast::create([
            'id' => (string) Str::uuid(),
            'title' => 'TL Broadcast',
            'sender_type' => TourLeader::class,
            'sender_id' => $tl->id,
            'message' => 'Pesan dari TL',
            'target_type' => 'kloter',
            'status' => 'published',
            'sent_at' => now(),
        ]);

        $this->assertInstanceOf(InternalUser::class, $adminBroadcast->fresh()->sender);
        $this->assertEquals($admin->id, $adminBroadcast->fresh()->sender->id);

        $this->assertInstanceOf(TourLeader::class, $tlBroadcast->fresh()->sender);
        $this->assertEquals($tl->id, $tlBroadcast->fresh()->sender->id);
    }

    public function test_jamaah_can_see_feed_and_detail_for_assigned_broadcast(): void
    {
        $admin = InternalUser::create(['id' => (string) Str::uuid(), 'full_name' => 'Admin']);
        $kloter = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLO-001', 'status' => 'active']);
        $jamaah = Jamaah::create(['id' => (string) Str::uuid(), 'kloter_id' => $kloter->id, 'status' => 'active', 'login_id' => 'JAMAAH01']);

        $broadcast = Broadcast::create([
            'id' => (string) Str::uuid(),
            'title' => 'Broadcast untuk Kloter 1',
            'sender_type' => InternalUser::class,
            'sender_id' => $admin->id,
            'message' => 'Pesan kloter 1',
            'target_type' => 'kloter',
            'status' => 'published',
            'sent_at' => now(),
        ]);

        Notification::create([
            'id' => (string) Str::uuid(),
            'broadcast_id' => $broadcast->id,
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah->id,
            'type' => 'broadcast',
            'title' => $broadcast->title,
            'message' => $broadcast->message,
            'delivery_status' => 'sent',
        ]);

        Sanctum::actingAs($jamaah, ['jamaah']);

        // Index
        $resIndex = $this->getJson('/api/broadcasts');
        $resIndex->assertOk();
        $this->assertCount(1, $resIndex->json('data.data'));
        $this->assertEquals($broadcast->id, $resIndex->json('data.data.0.id'));

        // Show
        $resShow = $this->getJson("/api/broadcasts/{$broadcast->id}");
        $resShow->assertOk();
        $this->assertEquals($broadcast->id, $resShow->json('data.broadcast.id'));
    }

    public function test_jamaah_cannot_see_feed_or_detail_of_unauthorized_broadcast(): void
    {
        $admin = InternalUser::create(['id' => (string) Str::uuid(), 'full_name' => 'Admin']);
        $kloter1 = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLO-001', 'status' => 'active']);
        $kloter2 = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLO-002', 'status' => 'active']);

        $jamaah1 = Jamaah::create(['id' => (string) Str::uuid(), 'kloter_id' => $kloter1->id, 'status' => 'active', 'login_id' => 'JAMAAH01']);
        $jamaah2 = Jamaah::create(['id' => (string) Str::uuid(), 'kloter_id' => $kloter2->id, 'status' => 'active', 'login_id' => 'JAMAAH02']);

        // Broadcast khusus kloter 2
        $broadcastKloter2 = Broadcast::create([
            'id' => (string) Str::uuid(),
            'title' => 'Broadcast Khusus Kloter 2',
            'sender_type' => InternalUser::class,
            'sender_id' => $admin->id,
            'message' => 'Pesan rahasia kloter 2',
            'target_type' => 'kloter',
            'status' => 'published',
            'sent_at' => now(),
        ]);

        Notification::create([
            'id' => (string) Str::uuid(),
            'broadcast_id' => $broadcastKloter2->id,
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah2->id,
            'type' => 'broadcast',
            'title' => $broadcastKloter2->title,
            'message' => $broadcastKloter2->message,
            'delivery_status' => 'sent',
        ]);

        // Login sebagai jamaah 1
        Sanctum::actingAs($jamaah1, ['jamaah']);

        // Index tidak memuat broadcast kloter 2
        $resIndex = $this->getJson('/api/broadcasts');
        $resIndex->assertOk();
        $this->assertCount(0, $resIndex->json('data.data'));

        // Show detail ditolak HTTP 403 Forbidden
        $resShow = $this->getJson("/api/broadcasts/{$broadcastKloter2->id}");
        $resShow->assertStatus(403);
    }

    public function test_family_with_active_relation_can_see_feed_and_detail_of_connected_jamaah(): void
    {
        $admin = InternalUser::create(['id' => (string) Str::uuid(), 'full_name' => 'Admin']);
        $kloter = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLO-001', 'status' => 'active']);
        $jamaah = Jamaah::create(['id' => (string) Str::uuid(), 'kloter_id' => $kloter->id, 'status' => 'active', 'login_id' => 'JAMAAH01']);

        // Relasi keluarga aktif
        FamilyRelation::create([
            'id' => (string) Str::uuid(),
            'jamaah_id' => $jamaah->id,
            'name' => 'Istri Jamaah',
            'relation_type' => 'spouse',
            'status' => 'active',
        ]);

        $broadcast = Broadcast::create([
            'id' => (string) Str::uuid(),
            'title' => 'Pengumuman Manasik',
            'sender_type' => InternalUser::class,
            'sender_id' => $admin->id,
            'message' => 'Jadwal manasik',
            'target_type' => 'kloter',
            'status' => 'published',
            'sent_at' => now(),
        ]);

        Notification::create([
            'id' => (string) Str::uuid(),
            'broadcast_id' => $broadcast->id,
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah->id,
            'type' => 'broadcast',
            'title' => $broadcast->title,
            'message' => $broadcast->message,
            'delivery_status' => 'sent',
        ]);

        // Login as Family (actingAs jamaah dengan token ability ['family'])
        Sanctum::actingAs($jamaah, ['family']);

        $resIndex = $this->getJson('/api/broadcasts');
        $resIndex->assertOk();
        $this->assertCount(1, $resIndex->json('data.data'));
        $this->assertEquals($broadcast->id, $resIndex->json('data.data.0.id'));

        $resShow = $this->getJson("/api/broadcasts/{$broadcast->id}");
        $resShow->assertOk();
        $this->assertEquals($broadcast->id, $resShow->json('data.broadcast.id'));
    }

    public function test_family_with_inactive_or_missing_relation_is_denied(): void
    {
        $admin = InternalUser::create(['id' => (string) Str::uuid(), 'full_name' => 'Admin']);
        $kloter = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLO-001', 'status' => 'active']);
        $jamaah = Jamaah::create(['id' => (string) Str::uuid(), 'kloter_id' => $kloter->id, 'status' => 'active', 'login_id' => 'JAMAAH01']);

        // Relasi status INACTIVE
        FamilyRelation::create([
            'id' => (string) Str::uuid(),
            'jamaah_id' => $jamaah->id,
            'name' => 'Kerabat Non-Aktif',
            'relation_type' => 'sibling',
            'status' => 'inactive',
        ]);

        $broadcast = Broadcast::create([
            'id' => (string) Str::uuid(),
            'title' => 'Pengumuman Penting',
            'sender_type' => InternalUser::class,
            'sender_id' => $admin->id,
            'message' => 'Pesan',
            'target_type' => 'kloter',
            'status' => 'published',
            'sent_at' => now(),
        ]);

        Notification::create([
            'id' => (string) Str::uuid(),
            'broadcast_id' => $broadcast->id,
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah->id,
            'type' => 'broadcast',
            'title' => $broadcast->title,
            'message' => $broadcast->message,
            'delivery_status' => 'sent',
        ]);

        Sanctum::actingAs($jamaah, ['family']);

        // Index ditolak HTTP 403
        $resIndex = $this->getJson('/api/broadcasts');
        $resIndex->assertStatus(403);

        // Show ditolak HTTP 403
        $resShow = $this->getJson("/api/broadcasts/{$broadcast->id}");
        $resShow->assertStatus(403);
    }

    public function test_family_cannot_see_broadcast_of_different_jamaah(): void
    {
        $admin = InternalUser::create(['id' => (string) Str::uuid(), 'full_name' => 'Admin']);
        $kloterA = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLO-A', 'status' => 'active']);
        $kloterB = Kloter::create(['id' => (string) Str::uuid(), 'code' => 'KLO-B', 'status' => 'active']);

        $jamaahA = Jamaah::create(['id' => (string) Str::uuid(), 'kloter_id' => $kloterA->id, 'status' => 'active', 'login_id' => 'JAMAAH-A']);
        $jamaahB = Jamaah::create(['id' => (string) Str::uuid(), 'kloter_id' => $kloterB->id, 'status' => 'active', 'login_id' => 'JAMAAH-B']);

        FamilyRelation::create([
            'id' => (string) Str::uuid(),
            'jamaah_id' => $jamaahA->id,
            'name' => 'Keluarga Jamaah A',
            'relation_type' => 'spouse',
            'status' => 'active',
        ]);

        // Broadcast hanya untuk Jamaah B
        $broadcastB = Broadcast::create([
            'id' => (string) Str::uuid(),
            'title' => 'Broadcast Khusus B',
            'sender_type' => InternalUser::class,
            'sender_id' => $admin->id,
            'message' => 'Pesan khusus B',
            'target_type' => 'kloter',
            'status' => 'published',
            'sent_at' => now(),
        ]);

        Notification::create([
            'id' => (string) Str::uuid(),
            'broadcast_id' => $broadcastB->id,
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaahB->id,
            'type' => 'broadcast',
            'title' => $broadcastB->title,
            'message' => $broadcastB->message,
            'delivery_status' => 'sent',
        ]);

        // Login as Family of Jamaah A
        Sanctum::actingAs($jamaahA, ['family']);

        $resIndex = $this->getJson('/api/broadcasts');
        $resIndex->assertOk();
        $this->assertCount(0, $resIndex->json('data.data'));

        $resShow = $this->getJson("/api/broadcasts/{$broadcastB->id}");
        $resShow->assertStatus(403);
    }
}
