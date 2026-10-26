<?php

namespace Tests\Feature;

use App\Models\FamilyRelation;
use App\Models\InternalUser;
use App\Models\Jamaah;
use App\Models\Notification;
use App\Models\Schedule;
use App\Models\TourLeader;
use App\Services\ScheduleTimelineService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleTimelineAndReminderTest extends TestCase
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
            $table->string('login_id', 50)->nullable();
            $table->string('full_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('family_relations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('jamaah_id')->constrained('jamaah')->cascadeOnDelete();
            $table->string('name', 150)->nullable();
            $table->string('relation_type', 50)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
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
            $table->enum('delivery_status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamps();
        });

        Schema::create('user_device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tokenable_type');
            $table->string('tokenable_id');
            $table->string('platform');
            $table->text('fcm_token');
            $table->string('device_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notification_device_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('notification_id');
            $table->uuid('device_token_id');
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('day_number')->default(1);
            $table->date('date');
            $table->time('time');
            $table->time('end_time')->nullable();
            $table->string('title');
            $table->string('category', 50)->nullable();
            $table->string('location')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('pic')->nullable();
            $table->string('status_override', 20)->nullable();
            $table->string('status_mode', 20)->default('auto');
            $table->string('status_updated_by_type')->nullable();
            $table->string('status_updated_by_id')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->string('reminder_status', 20)->default('draft');
            $table->timestamp('broadcasted_at')->nullable();
            $table->string('broadcasted_by_type')->nullable();
            $table->string('broadcasted_by_id')->nullable();
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
        Schema::dropIfExists('schedules');
        Schema::dropIfExists('notification_device_deliveries');
        Schema::dropIfExists('user_device_tokens');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('family_relations');
        Schema::dropIfExists('jamaah');
        Schema::dropIfExists('tour_leaders');
        Schema::dropIfExists('internal_users');
    }

    // ==========================================
    // 1. STATUS & TIME CALCULATION TESTS
    // ==========================================

    public function test_schedule_time_and_inferred_status_rules(): void
    {
        // Setup example from Section 3B:
        // Schedule 1: 20:00 (end empty, inferred as 21:00)
        // Schedule 2: 21:00 - 22:00
        // Schedule 3: 22:30 - 23:00

        $date = '2026-09-10';
        $s1 = Schedule::create([
            'date' => $date,
            'time' => '20:00',
            'end_time' => null,
            'title' => 'Persiapan keberangkatan',
        ]);

        $s2 = Schedule::create([
            'date' => $date,
            'time' => '21:00',
            'end_time' => '22:00',
            'title' => 'Berkumpul di lobi',
        ]);

        $s3 = Schedule::create([
            'date' => $date,
            'time' => '22:30',
            'end_time' => '23:00',
            'title' => 'Keberangkatan',
        ]);

        $service = app(ScheduleTimelineService::class);

        // Test at 21:00:00
        $testTimeAt2100 = Carbon::parse("{$date} 21:00:00");
        $resolvedAt2100 = $service->resolveTimeline(Schedule::all(), $testTimeAt2100);

        $res1 = $resolvedAt2100->firstWhere('schedule.id', $s1->id);
        $res2 = $resolvedAt2100->firstWhere('schedule.id', $s2->id);
        $res3 = $resolvedAt2100->firstWhere('schedule.id', $s3->id);

        $this->assertEquals('21:00', $res1['inferred_end_time']);
        $this->assertEquals('21:00', $res1['effective_end_time']);
        $this->assertEquals('completed', $res1['status'], 'S1 should be completed at 21:00 because inferred end is 21:00');

        $this->assertNull($res2['inferred_end_time']);
        $this->assertEquals('22:00', $res2['effective_end_time']);
        $this->assertEquals('in_progress', $res2['status'], 'S2 should be in_progress at 21:00');

        $this->assertEquals('upcoming', $res3['status'], 'S3 should be upcoming at 21:00');

        // Test at 20:30:00: S1 is in_progress
        $testTimeAt2030 = Carbon::parse("{$date} 20:30:00");
        $resolvedAt2030 = $service->resolveTimeline(Schedule::all(), $testTimeAt2030);
        $this->assertEquals('in_progress', $resolvedAt2030->firstWhere('schedule.id', $s1->id)['status']);

        // Test at 19:30:00: S1 is upcoming
        $testTimeAt1930 = Carbon::parse("{$date} 19:30:00");
        $resolvedAt1930 = $service->resolveTimeline(Schedule::all(), $testTimeAt1930);
        $this->assertEquals('upcoming', $resolvedAt1930->firstWhere('schedule.id', $s1->id)['status']);
    }

    public function test_last_schedule_without_end_time_does_not_invent_artificial_end_time(): void
    {
        $date = '2026-09-10';
        $s1 = Schedule::create([
            'date' => $date,
            'time' => '10:00',
            'end_time' => null,
            'title' => 'Single Activity',
        ]);

        $service = app(ScheduleTimelineService::class);

        // At 12:00 (after start)
        $resolved = $service->resolveTimeline(Schedule::all(), Carbon::parse("{$date} 12:00:00"));
        $res = $resolved->first();

        $this->assertNull($res['inferred_end_time']);
        $this->assertNull($res['effective_end_time']);
        $this->assertEquals('in_progress', $res['status'], 'Without next schedule, should remain in_progress and not invent end time');
    }

    public function test_inserting_or_deleting_next_schedule_recalculates_inferred_boundary(): void
    {
        $date = '2026-09-10';
        $s1 = Schedule::create([
            'date' => $date,
            'time' => '10:00',
            'end_time' => null,
            'title' => 'Activity 1',
        ]);

        $service = app(ScheduleTimelineService::class);

        // Initially no next schedule
        $resolved1 = $service->resolveTimeline(Schedule::all(), Carbon::parse("{$date} 11:30:00"));
        $this->assertEquals('in_progress', $resolved1->first()['status']);
        $this->assertNull($resolved1->first()['effective_end_time']);

        // Insert next schedule at 11:00
        $s2 = Schedule::create([
            'date' => $date,
            'time' => '11:00',
            'end_time' => '12:00',
            'title' => 'Activity 2',
        ]);

        // At 11:30, S1 should now be completed because inferred end time is 11:00
        $resolved2 = $service->resolveTimeline(Schedule::all(), Carbon::parse("{$date} 11:30:00"));
        $this->assertEquals('completed', $resolved2->firstWhere('schedule.id', $s1->id)['status']);
        $this->assertEquals('11:00', $resolved2->firstWhere('schedule.id', $s1->id)['effective_end_time']);

        // Delete S2 -> S1 reverts back to having no next schedule
        $s2->delete();
        $resolved3 = $service->resolveTimeline(Schedule::all(), Carbon::parse("{$date} 11:30:00"));
        $this->assertEquals('in_progress', $resolved3->firstWhere('schedule.id', $s1->id)['status']);
        $this->assertNull($resolved3->firstWhere('schedule.id', $s1->id)['effective_end_time']);
    }

    public function test_manual_status_override_is_not_overwritten_by_auto_calculation(): void
    {
        $date = '2026-09-10';
        $s1 = Schedule::create([
            'date' => $date,
            'time' => '20:00',
            'end_time' => '21:00',
            'title' => 'Manual completed early',
            'status_override' => 'completed',
            'status_mode' => 'manual',
        ]);

        $service = app(ScheduleTimelineService::class);

        // Even at 19:00 (before start), manual status 'completed' must be honored
        $resolved = $service->resolveTimeline(Schedule::all(), Carbon::parse("{$date} 19:00:00"));
        $this->assertEquals('completed', $resolved->first()['status']);
    }

    // ==========================================
    // 2. ROLE AUTHORIZATION TESTS
    // ==========================================

    public function test_admin_can_perform_all_crud_and_status(): void
    {
        $admin = InternalUser::create(['id' => (string) Str::uuid(), 'full_name' => 'Admin User']);
        Sanctum::actingAs($admin, ['admin']);

        // Create
        $response = $this->postJson('/api/schedules', [
            'date' => '2026-09-15',
            'time' => '08:00',
            'title' => 'Tawaf Wada',
            'category' => 'Ibadah',
        ]);
        $response->assertCreated();
        $scheduleId = $response->json('data.id');

        // Read Index
        $this->getJson('/api/schedules')->assertOk();

        // Read Show
        $this->getJson("/api/schedules/{$scheduleId}")->assertOk();

        // Update
        $this->putJson("/api/schedules/{$scheduleId}", [
            'title' => 'Tawaf Wada Updated',
        ])->assertOk();

        // Update Status
        $this->patchJson("/api/schedules/{$scheduleId}/status", [
            'status_override' => 'in_progress',
            'mode' => 'manual',
        ])->assertOk()->assertJsonPath('data.status', 'in_progress');

        // Delete
        $this->deleteJson("/api/schedules/{$scheduleId}")->assertOk();
    }

    public function test_tour_leader_can_create_read_update_status_and_broadcast(): void
    {
        $tl = TourLeader::create(['id' => (string) Str::uuid(), 'full_name' => 'TL Ahmad']);
        Sanctum::actingAs($tl, ['tour_leader']);

        // TL can create schedule
        $createRes = $this->postJson('/api/schedules', [
            'date' => '2026-09-16',
            'time' => '09:00',
            'title' => 'City Tour',
        ]);
        $createRes->assertCreated();
        $scheduleId = $createRes->json('data.id');

        // TL can read
        $this->getJson('/api/schedules')->assertOk();

        // TL can update status
        $this->patchJson("/api/schedules/{$scheduleId}/status", [
            'status_override' => 'completed',
        ])->assertOk()->assertJsonPath('data.status', 'completed');

        // TL can broadcast
        $this->postJson("/api/schedules/{$scheduleId}/broadcast")->assertOk()
            ->assertJsonPath('data.schedule.reminder_status', 'published');

        // TL CANNOT update other fields
        $this->putJson("/api/schedules/{$scheduleId}", ['title' => 'Hacked Title'])->assertForbidden();

        // TL CANNOT delete schedule
        $this->deleteJson("/api/schedules/{$scheduleId}")->assertForbidden();
    }

    public function test_jamaah_and_family_can_only_read_and_cannot_write(): void
    {
        $jamaah = Jamaah::create(['id' => (string) Str::uuid(), 'full_name' => 'Jamaah Budi', 'status' => 'active']);
        $schedule = Schedule::create([
            'date' => '2026-09-17',
            'time' => '10:00',
            'title' => 'Manasik',
        ]);

        // As Jamaah
        Sanctum::actingAs($jamaah, ['jamaah']);
        $this->getJson('/api/schedules')->assertOk();
        $this->getJson("/api/schedules/{$schedule->id}")->assertOk();
        $this->postJson('/api/schedules', ['date' => '2026-09-17', 'time' => '11:00', 'title' => 'Fail'])->assertForbidden();
        $this->putJson("/api/schedules/{$schedule->id}", ['title' => 'Fail'])->assertForbidden();
        $this->patchJson("/api/schedules/{$schedule->id}/status", ['status_override' => 'completed'])->assertForbidden();
        $this->postJson("/api/schedules/{$schedule->id}/broadcast")->assertForbidden();
        $this->deleteJson("/api/schedules/{$schedule->id}")->assertForbidden();

        // As Family
        Sanctum::actingAs($jamaah, ['family']);
        $this->getJson('/api/schedules')->assertOk();
        $this->getJson("/api/schedules/{$schedule->id}")->assertOk();
        $this->postJson('/api/schedules', ['date' => '2026-09-17', 'time' => '11:00', 'title' => 'Fail'])->assertForbidden();
        $this->putJson("/api/schedules/{$schedule->id}", ['title' => 'Fail'])->assertForbidden();
        $this->patchJson("/api/schedules/{$schedule->id}/status", ['status_override' => 'completed'])->assertForbidden();
        $this->postJson("/api/schedules/{$schedule->id}/broadcast")->assertForbidden();
        $this->deleteJson("/api/schedules/{$schedule->id}")->assertForbidden();
    }

    // ==========================================
    // 3. REMINDER & RECIPIENT ELIGIBILITY TESTS
    // ==========================================

    public function test_draft_schedule_remains_visible_on_timeline(): void
    {
        $schedule = Schedule::create([
            'date' => '2026-09-18',
            'time' => '14:00',
            'title' => 'Draft Schedule',
            'reminder_status' => 'draft',
        ]);

        $jamaah = Jamaah::create(['id' => (string) Str::uuid(), 'full_name' => 'Jamaah Test']);
        Sanctum::actingAs($jamaah, ['jamaah']);

        $res = $this->getJson('/api/schedules')->assertOk();
        $this->assertNotEmpty($res->json('data'));
        $this->assertEquals('draft', $res->json('data.0.reminder_status'));
    }

    public function test_family_and_jamaah_recipient_filtering_rules(): void
    {
        // 1. Active Jamaah
        $activeJamaah = Jamaah::create(['id' => (string) Str::uuid(), 'full_name' => 'Active Jamaah', 'status' => 'active']);

        // 2. Inactive Jamaah
        $inactiveJamaah = Jamaah::create(['id' => (string) Str::uuid(), 'full_name' => 'Inactive Jamaah', 'status' => 'inactive']);

        // 3. Family active linked to active Jamaah -> Eligible
        $eligibleFamily = FamilyRelation::create([
            'id' => (string) Str::uuid(),
            'jamaah_id' => $activeJamaah->id,
            'name' => 'Wife of Active Jamaah',
            'status' => 'active',
        ]);

        // 4. Family inactive linked to active Jamaah -> Not eligible
        $inactiveFamily = FamilyRelation::create([
            'id' => (string) Str::uuid(),
            'jamaah_id' => $activeJamaah->id,
            'name' => 'Inactive Family Member',
            'status' => 'inactive',
        ]);

        // 5. Family active linked to inactive Jamaah -> Not eligible
        $familyOfInactiveJamaah = FamilyRelation::create([
            'id' => (string) Str::uuid(),
            'jamaah_id' => $inactiveJamaah->id,
            'name' => 'Family of Inactive Jamaah',
            'status' => 'active',
        ]);

        $reminderService = app(\App\Services\ScheduleReminderService::class);

        $this->assertTrue($reminderService->isFamilyEligible($eligibleFamily));
        $this->assertFalse($reminderService->isFamilyEligible($inactiveFamily));
        $this->assertFalse($reminderService->isFamilyEligible($familyOfInactiveJamaah));

        $recipients = $reminderService->getEligibleRecipients();

        // Check active Jamaah is present
        $this->assertTrue($recipients->contains('recipient_id', (string) $activeJamaah->id));
        // Check inactive Jamaah is absent
        $this->assertFalse($recipients->contains('recipient_id', (string) $inactiveJamaah->id));
    }

    public function test_broadcast_records_audit_and_deduplicates_notifications(): void
    {
        $admin = InternalUser::create(['id' => (string) Str::uuid(), 'full_name' => 'Admin Boss', 'status' => 'active']);
        $activeJamaah = Jamaah::create(['id' => (string) Str::uuid(), 'full_name' => 'Active Jamaah', 'status' => 'active']);

        $schedule = Schedule::create([
            'date' => '2026-09-20',
            'time' => '07:00',
            'title' => 'Shalat Id',
        ]);

        Sanctum::actingAs($admin, ['admin']);

        // First broadcast
        $res1 = $this->postJson("/api/schedules/{$schedule->id}/broadcast")->assertOk();
        $this->assertEquals('published', $res1->json('data.schedule.reminder_status'));
        $this->assertEquals(InternalUser::class, $res1->json('data.schedule.broadcasted_by_type'));
        $this->assertEquals((string) $admin->id, $res1->json('data.schedule.broadcasted_by_id'));

        $notifCountAfterFirst = Notification::where('type', 'schedule_reminder')->count();
        $this->assertGreaterThan(0, $notifCountAfterFirst);

        // Immediate repeat broadcast (idempotent / deduplication)
        $res2 = $this->postJson("/api/schedules/{$schedule->id}/broadcast")->assertOk();
        $notifCountAfterSecond = Notification::where('type', 'schedule_reminder')->count();

        $this->assertEquals($notifCountAfterFirst, $notifCountAfterSecond, 'Repeated broadcast within 1 hour should not duplicate notification records');
    }
}
