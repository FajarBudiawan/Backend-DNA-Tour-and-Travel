<?php

namespace Tests\Feature;

use App\Models\InternalUser;
use App\Models\Jamaah;
use App\Models\Notification;
use App\Models\TourLeader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
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

        Schema::create('jamaah', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
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
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('jamaah');
        Schema::dropIfExists('tour_leaders');
        Schema::dropIfExists('internal_users');
    }

    public function test_tour_leader_only_receives_their_own_notifications(): void
    {
        $tl1 = new TourLeader();
        $tl1->id = (string) Str::uuid();
        $tl1->save();

        $tl2 = new TourLeader();
        $tl2->id = (string) Str::uuid();
        $tl2->save();

        $notif1 = Notification::create([
            'id' => (string) Str::uuid(),
            'recipient_type' => TourLeader::class,
            'recipient_id' => $tl1->id,
            'type' => 'sos_created',
            'title' => 'Notif TL 1',
            'message' => 'Pesan 1',
        ]);

        $notif2 = Notification::create([
            'id' => (string) Str::uuid(),
            'recipient_type' => TourLeader::class,
            'recipient_id' => $tl2->id,
            'type' => 'sos_created',
            'title' => 'Notif TL 2',
            'message' => 'Pesan 2',
        ]);

        Sanctum::actingAs($tl1, ['tour_leader']);

        $res = $this->getJson('/api/notifications');
        $res->assertStatus(200);
        $res->assertJsonPath('data.total', 1);
        $res->assertJsonPath('data.data.0.id', $notif1->id);
    }

    public function test_notifications_pagination_and_empty_state(): void
    {
        $tl = new TourLeader();
        $tl->id = (string) Str::uuid();
        $tl->save();

        Sanctum::actingAs($tl, ['tour_leader']);

        // Empty state
        $res = $this->getJson('/api/notifications');
        $res->assertStatus(200);
        $res->assertJsonPath('data.total', 0);
        $this->assertEmpty($res->json('data.data'));
    }

    public function test_mark_as_read_succeeds_for_owner_and_forbidden_for_other(): void
    {
        $tl1 = new TourLeader();
        $tl1->id = (string) Str::uuid();
        $tl1->save();

        $tl2 = new TourLeader();
        $tl2->id = (string) Str::uuid();
        $tl2->save();

        $notif = Notification::create([
            'id' => (string) Str::uuid(),
            'recipient_type' => TourLeader::class,
            'recipient_id' => $tl1->id,
            'type' => 'sos_created',
            'title' => 'Notif TL 1',
            'message' => 'Pesan 1',
        ]);

        // TL2 cannot mark TL1's notif as read
        Sanctum::actingAs($tl2, ['tour_leader']);
        $this->patchJson("/api/notifications/{$notif->id}/read")->assertStatus(403);

        // TL1 can mark as read
        Sanctum::actingAs($tl1, ['tour_leader']);
        $this->patchJson("/api/notifications/{$notif->id}/read")->assertStatus(200);
        $this->assertNotNull($notif->fresh()->read_at);
    }

    public function test_family_role_can_retrieve_and_mark_notifications_for_connected_jamaah(): void
    {
        $jamaah = new Jamaah();
        $jamaah->id = (string) Str::uuid();
        $jamaah->full_name = 'Jamaah H. Ahmad';
        $jamaah->save();

        $notif = Notification::create([
            'id' => (string) Str::uuid(),
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah->id,
            'type' => 'broadcast',
            'title' => 'Pengumuman Penting',
            'message' => 'Pesan untuk jamaah dan keluarga',
        ]);

        // Login as family of this jamaah
        Sanctum::actingAs($jamaah, ['family']);

        $res = $this->getJson('/api/notifications');
        $res->assertOk();
        $res->assertJsonPath('data.total', 1);
        $res->assertJsonPath('data.data.0.id', $notif->id);

        // Family can mark as read
        $patchRes = $this->patchJson("/api/notifications/{$notif->id}/read");
        $patchRes->assertOk();
        $this->assertNotNull($notif->fresh()->read_at);
    }
}
