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
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(
                ['notification_id', 'device_token_id'],
                'test_deliveries_unique'
            );
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

        // Test at 21:00:00 WIB — schedule times are stored as WIB, so test time must also be WIB
        $testTimeAt2100 = Carbon::parse("{$date} 21:00:00", 'Asia/Jakarta');
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

        // Test at 20:30:00 WIB: S1 is in_progress
        $testTimeAt2030 = Carbon::parse("{$date} 20:30:00", 'Asia/Jakarta');
        $resolvedAt2030 = $service->resolveTimeline(Schedule::all(), $testTimeAt2030);
        $this->assertEquals('in_progress', $resolvedAt2030->firstWhere('schedule.id', $s1->id)['status']);

        // Test at 19:30:00 WIB: S1 is upcoming
        $testTimeAt1930 = Carbon::parse("{$date} 19:30:00", 'Asia/Jakarta');
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

        // At 12:00 WIB (after start) — use WIB timezone to match service parsing
        $resolved = $service->resolveTimeline(Schedule::all(), Carbon::parse("{$date} 12:00:00", 'Asia/Jakarta'));
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

        // Initially no next schedule — use WIB timezone to match service parsing
        $resolved1 = $service->resolveTimeline(Schedule::all(), Carbon::parse("{$date} 11:30:00", 'Asia/Jakarta'));
        $this->assertEquals('in_progress', $resolved1->first()['status']);
        $this->assertNull($resolved1->first()['effective_end_time']);

        // Insert next schedule at 11:00
        $s2 = Schedule::create([
            'date' => $date,
            'time' => '11:00',
            'end_time' => '12:00',
            'title' => 'Activity 2',
        ]);

        // At 11:30 WIB, S1 should now be completed because inferred end time is 11:00
        $resolved2 = $service->resolveTimeline(Schedule::all(), Carbon::parse("{$date} 11:30:00", 'Asia/Jakarta'));
        $this->assertEquals('completed', $resolved2->firstWhere('schedule.id', $s1->id)['status']);
        $this->assertEquals('11:00', $resolved2->firstWhere('schedule.id', $s1->id)['effective_end_time']);

        // Delete S2 -> S1 reverts back to having no next schedule
        $s2->delete();
        $resolved3 = $service->resolveTimeline(Schedule::all(), Carbon::parse("{$date} 11:30:00", 'Asia/Jakarta'));
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

        // Even at 19:00 WIB (before start), manual status 'completed' must be honored
        $resolved = $service->resolveTimeline(Schedule::all(), Carbon::parse("{$date} 19:00:00", 'Asia/Jakarta'));
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

    // ==========================================
    // 4. TL-INT-02 — AUTOMATIC STATUS TRANSITIONS
    // ==========================================

    /**
     * TL-INT-02A — Belum mencapai waktu mulai: status tetap upcoming.
     *
     * Carbon::setTestNow() in WIB to simulate "before start time".
     * Schedule times are stored as WIB; the service parses them as Asia/Jakarta.
     */
    public function test_tlint02a_before_start_time_status_is_upcoming(): void
    {
        // Schedule at 10:00 WIB on a fixed date
        $date = '2026-11-01';
        $schedule = Schedule::create([
            'date'        => $date,
            'time'        => '10:00',
            'end_time'    => '11:00',
            'title'       => 'TL-INT-02A',
            'status_mode' => 'auto',
        ]);

        $service = app(ScheduleTimelineService::class);

        // Simulate current time = 09:59 WIB (1 minute before start)
        $beforeStart = Carbon::parse("{$date} 09:59:00", 'Asia/Jakarta');
        $resolved = $service->resolveTimeline(Schedule::all(), $beforeStart);
        $item = $resolved->first(fn($i) => $i['schedule']->id === $schedule->id);

        $this->assertEquals('upcoming', $item['status'], 'TL-INT-02A: 1 minute before start must be upcoming');
    }

    /**
     * TL-INT-02B — Waktu mulai telah tercapai: status berubah menjadi in_progress.
     */
    public function test_tlint02b_at_start_time_status_becomes_in_progress(): void
    {
        $date = '2026-11-01';
        $schedule = Schedule::create([
            'date'        => $date,
            'time'        => '10:00',
            'end_time'    => '11:00',
            'title'       => 'TL-INT-02B',
            'status_mode' => 'auto',
        ]);

        $service = app(ScheduleTimelineService::class);

        // Exactly at start time (WIB)
        $atStart = Carbon::parse("{$date} 10:00:00", 'Asia/Jakarta');
        $resolved = $service->resolveTimeline(Schedule::all(), $atStart);
        $item = $resolved->first(fn($i) => $i['schedule']->id === $schedule->id);
        $this->assertEquals('in_progress', $item['status'], 'TL-INT-02B: exactly at start time must be in_progress');

        // One minute after start (WIB)
        $afterStart = Carbon::parse("{$date} 10:01:00", 'Asia/Jakarta');
        $resolved2 = $service->resolveTimeline(Schedule::all(), $afterStart);
        $item2 = $resolved2->first(fn($i) => $i['schedule']->id === $schedule->id);
        $this->assertEquals('in_progress', $item2['status'], 'TL-INT-02B: 1 minute after start must be in_progress');
    }

    /**
     * TL-INT-02C — Waktu selesai telah tercapai: status berubah menjadi completed.
     */
    public function test_tlint02c_at_end_time_status_becomes_completed(): void
    {
        $date = '2026-11-01';
        $schedule = Schedule::create([
            'date'        => $date,
            'time'        => '10:00',
            'end_time'    => '11:00',
            'title'       => 'TL-INT-02C',
            'status_mode' => 'auto',
        ]);

        $service = app(ScheduleTimelineService::class);

        // Exactly at end time (WIB)
        $atEnd = Carbon::parse("{$date} 11:00:00", 'Asia/Jakarta');
        $resolved = $service->resolveTimeline(Schedule::all(), $atEnd);
        $item = $resolved->first(fn($i) => $i['schedule']->id === $schedule->id);
        $this->assertEquals('completed', $item['status'], 'TL-INT-02C: exactly at end time must be completed');

        // After end time (WIB)
        $afterEnd = Carbon::parse("{$date} 11:30:00", 'Asia/Jakarta');
        $resolved2 = $service->resolveTimeline(Schedule::all(), $afterEnd);
        $item2 = $resolved2->first(fn($i) => $i['schedule']->id === $schedule->id);
        $this->assertEquals('completed', $item2['status'], 'TL-INT-02C: after end time must be completed');
    }

    /**
     * TL-INT-02D — Mode manual: scheduler tidak menimpa status manual.
     */
    public function test_tlint02d_manual_mode_not_overridden_by_auto_calculation(): void
    {
        $date = '2026-11-01';
        $schedule = Schedule::create([
            'date'            => $date,
            'time'            => '10:00',
            'end_time'        => '11:00',
            'title'           => 'TL-INT-02D Manual',
            'status_mode'     => 'manual',
            'status_override' => 'upcoming',
        ]);

        $service = app(ScheduleTimelineService::class);

        // Even if current time is well past end time, manual override is preserved
        $wellAfterEnd = Carbon::parse("{$date} 15:00:00", 'Asia/Jakarta');
        $resolved = $service->resolveTimeline(Schedule::all(), $wellAfterEnd);
        $item = $resolved->first(fn($i) => $i['schedule']->id === $schedule->id);
        $this->assertEquals('upcoming', $item['status'], 'TL-INT-02D: manual status_override must be preserved');

        // Also verify with in_progress override
        $schedule->update(['status_override' => 'in_progress']);
        $resolved2 = $service->resolveTimeline(Schedule::all()->fresh(), $wellAfterEnd);
        $item2 = $resolved2->first(fn($i) => $i['schedule']->id === $schedule->id);
        $this->assertEquals('in_progress', $item2['status'], 'TL-INT-02D: in_progress manual override must be preserved');
    }

    /**
     * TL-INT-02E — Inferred end time dari jadwal berikutnya.
     */
    public function test_tlint02e_inferred_end_time_from_next_schedule(): void
    {
        $date = '2026-11-01';
        // S1: no end_time — inferred from S2's start
        $s1 = Schedule::create([
            'date'     => $date,
            'time'     => '08:00',
            'end_time' => null,
            'title'    => 'TL-INT-02E S1',
        ]);
        // S2: starts at 09:00 — this is the inferred end for S1
        $s2 = Schedule::create([
            'date'     => $date,
            'time'     => '09:00',
            'end_time' => '10:00',
            'title'    => 'TL-INT-02E S2',
        ]);

        $service = app(ScheduleTimelineService::class);

        // At 08:30 WIB: S1 in_progress, inferred_end = 09:00
        $midS1 = Carbon::parse("{$date} 08:30:00", 'Asia/Jakarta');
        $resolved = $service->resolveTimeline(Schedule::all(), $midS1);

        $r1 = $resolved->first(fn($i) => $i['schedule']->id === $s1->id);
        $this->assertEquals('09:00', $r1['inferred_end_time'], 'TL-INT-02E: inferred_end_time must be next schedule start time');
        $this->assertEquals('09:00', $r1['effective_end_time'], 'TL-INT-02E: effective_end_time must equal inferred end');
        $this->assertEquals('in_progress', $r1['status'], 'TL-INT-02E: S1 must be in_progress at 08:30');

        // At 09:00 WIB: S1 completed (inferred end reached)
        $atInferredEnd = Carbon::parse("{$date} 09:00:00", 'Asia/Jakarta');
        $resolved2 = $service->resolveTimeline(Schedule::all(), $atInferredEnd);
        $r1b = $resolved2->first(fn($i) => $i['schedule']->id === $s1->id);
        $this->assertEquals('completed', $r1b['status'], 'TL-INT-02E: S1 must be completed when inferred end is reached');
    }

    /**
     * TL-INT-02F — Jadwal terakhir tanpa end_time: tetap in_progress setelah mulai.
     * Desain: tidak menginvent end time jika tidak ada jadwal berikutnya.
     */
    public function test_tlint02f_last_schedule_without_end_time_remains_in_progress(): void
    {
        $date = '2026-11-01';
        $schedule = Schedule::create([
            'date'     => $date,
            'time'     => '20:00',
            'end_time' => null,
            'title'    => 'TL-INT-02F Last',
        ]);

        $service = app(ScheduleTimelineService::class);

        $afterStart = Carbon::parse("{$date} 22:00:00", 'Asia/Jakarta');
        $resolved = $service->resolveTimeline(Schedule::all(), $afterStart);
        $item = $resolved->first(fn($i) => $i['schedule']->id === $schedule->id);

        $this->assertNull($item['inferred_end_time'], 'TL-INT-02F: last schedule with no end_time must not invent inferred_end_time');
        $this->assertNull($item['effective_end_time'], 'TL-INT-02F: effective_end_time must be null');
        $this->assertEquals('in_progress', $item['status'], 'TL-INT-02F: last schedule after start, no end = in_progress');
    }

    /**
     * TL-INT-02G — Batas tanggal dan timezone WIB.
     * Memastikan jam WIB tidak digeser 7 jam ke UTC saat perbandingan status.
     */
    public function test_tlint02g_timezone_wib_boundary_no_7hour_shift(): void
    {
        // Critical scenario: schedule at 22:55 WIB.
        // Bug before fix: Carbon::parse('... 22:55') treated as UTC → start appears 7 hours later.
        $date = '2026-11-15';
        $schedule = Schedule::create([
            'date'        => $date,
            'time'        => '22:55',
            'end_time'    => '23:30',
            'title'       => 'TL-INT-02G WIB Boundary',
            'status_mode' => 'auto',
        ]);

        $service = app(ScheduleTimelineService::class);

        // 22:54 WIB → still upcoming (1 minute before)
        $justBefore = Carbon::parse("{$date} 22:54:00", 'Asia/Jakarta');
        $resolved = $service->resolveTimeline(Schedule::all(), $justBefore);
        $item = $resolved->first(fn($i) => $i['schedule']->id === $schedule->id);
        $this->assertEquals('upcoming', $item['status'], 'TL-INT-02G: 1 min before 22:55 WIB must be upcoming');

        // 22:55 WIB → in_progress (start reached in WIB, not UTC+7 offset)
        $atStart = Carbon::parse("{$date} 22:55:00", 'Asia/Jakarta');
        $resolved2 = $service->resolveTimeline(Schedule::all(), $atStart);
        $item2 = $resolved2->first(fn($i) => $i['schedule']->id === $schedule->id);
        $this->assertEquals('in_progress', $item2['status'], 'TL-INT-02G: at 22:55 WIB must be in_progress (not upcoming due to UTC shift bug)');

        // Midnight crossing test: schedule at 23:00 → end at 00:30 next day
        $date2 = '2026-11-20';
        $sMidnight = Schedule::create([
            'date'        => $date2,
            'time'        => '23:00',
            'end_time'    => '00:30', // crosses midnight
            'title'       => 'TL-INT-02G Midnight Cross',
            'status_mode' => 'auto',
        ]);

        // At 23:30 WIB same day → in_progress (not completed, end is next day 00:30)
        $midNight = Carbon::parse("{$date2} 23:30:00", 'Asia/Jakarta');
        $resolved3 = $service->resolveTimeline(Schedule::all(), $midNight);
        $itemM = $resolved3->first(fn($i) => $i['schedule']->id === $sMidnight->id);
        $this->assertEquals('in_progress', $itemM['status'], 'TL-INT-02G: midnight crossing — at 23:30 WIB before 00:30 end must be in_progress');

        // At 00:31 WIB next day → completed
        $nextDay = Carbon::parse($date2, 'Asia/Jakarta')->addDay()->setTime(0, 31, 0);
        $resolved4 = $service->resolveTimeline(Schedule::all(), $nextDay);
        $itemM2 = $resolved4->first(fn($i) => $i['schedule']->id === $sMidnight->id);
        $this->assertEquals('completed', $itemM2['status'], 'TL-INT-02G: midnight crossing — at 00:31 WIB next day must be completed');
    }

    /**
     * TL-INT-02H — Idempotensi: menjalankan resolveTimeline berkali-kali tidak mengubah hasil.
     */
    public function test_tlint02h_idempotent_status_resolution(): void
    {
        $date = '2026-11-01';
        Schedule::create(['date' => $date, 'time' => '08:00', 'end_time' => '09:00', 'title' => 'TL-INT-02H S1']);
        Schedule::create(['date' => $date, 'time' => '09:00', 'end_time' => '10:00', 'title' => 'TL-INT-02H S2']);
        Schedule::create(['date' => $date, 'time' => '10:00', 'end_time' => null,    'title' => 'TL-INT-02H S3 (last)']);

        $service = app(ScheduleTimelineService::class);
        $fixedNow = Carbon::parse("{$date} 09:30:00", 'Asia/Jakarta');

        $run1 = $service->resolveTimeline(Schedule::all(), $fixedNow)->map(fn($i) => $i['status'])->values()->toArray();
        $run2 = $service->resolveTimeline(Schedule::all(), $fixedNow)->map(fn($i) => $i['status'])->values()->toArray();
        $run3 = $service->resolveTimeline(Schedule::all(), $fixedNow)->map(fn($i) => $i['status'])->values()->toArray();

        $this->assertEquals($run1, $run2, 'TL-INT-02H: second run must produce same result');
        $this->assertEquals($run2, $run3, 'TL-INT-02H: third run must produce same result');

        // Verify expected statuses at 09:30: S1=completed, S2=in_progress, S3=upcoming
        $this->assertEquals(['completed', 'in_progress', 'upcoming'], $run1, 'TL-INT-02H: expected statuses at 09:30 WIB');
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

    // ==========================================
    // 5. TL-INT-03 — SCHEDULE REMINDER PUSH DELIVERY TESTS
    // ==========================================

    public function test_tlint03_job_processes_tokens_and_records_delivery_successfully(): void
    {
        $schedule = Schedule::create([
            'date' => '2026-10-30',
            'time' => '10:00',
            'title' => 'Kajian Akbar',
        ]);

        $jamaah = Jamaah::create(['id' => (string) Str::uuid(), 'full_name' => 'Jamaah Sukses']);
        $token = \App\Models\DeviceToken::create([
            'id' => (string) Str::uuid(),
            'tokenable_type' => Jamaah::class,
            'tokenable_id' => $jamaah->id,
            'fcm_token' => 'valid_fcm_token_123',
            'platform' => 'android',
            'is_active' => true,
        ]);

        $notification = Notification::create([
            'id' => (string) Str::uuid(),
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah->id,
            'type' => 'schedule_reminder',
            'title' => 'Pengingat Jadwal: Kajian Akbar',
            'message' => 'Detail kajian',
            'delivery_status' => 'pending',
        ]);

        $fcmMock = \Mockery::mock(\App\Services\FcmService::class);
        $fcmMock->shouldReceive('send')
            ->once()
            ->with('valid_fcm_token_123', \Mockery::on(function ($payload) use ($notification, $schedule) {
                return $payload['title'] === $notification->title
                    && $payload['data']['schedule_id'] === (string) $schedule->id
                    && $payload['data']['notification_id'] === $notification->id;
            }))
            ->andReturn(['name' => 'projects/test/messages/msg_123']);

        $job = new \App\Jobs\ProcessScheduleReminderJob($schedule, [$notification->id]);
        $job->handle($fcmMock);

        $notification->refresh();
        $this->assertEquals('sent', $notification->delivery_status);
        $this->assertNotNull($notification->sent_at);

        $delivery = \App\Models\NotificationDeviceDelivery::where('notification_id', $notification->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('sent', $delivery->status);
        $this->assertEquals(1, $delivery->attempts);
        $this->assertNull($delivery->last_error);

        $token->refresh();
        $this->assertTrue($token->is_active);
        $this->assertNotNull($token->last_used_at);
    }

    public function test_tlint03_job_deactivates_invalid_token_and_marks_delivery_failed_permanently(): void
    {
        $schedule = Schedule::create([
            'date' => '2026-10-30',
            'time' => '10:00',
            'title' => 'Kajian Akbar',
        ]);

        $jamaah = Jamaah::create(['id' => (string) Str::uuid(), 'full_name' => 'Jamaah Token Mati']);
        $token = \App\Models\DeviceToken::create([
            'id' => (string) Str::uuid(),
            'tokenable_type' => Jamaah::class,
            'tokenable_id' => $jamaah->id,
            'fcm_token' => 'invalid_fcm_token_999',
            'platform' => 'android',
            'is_active' => true,
        ]);

        $notification = Notification::create([
            'id' => (string) Str::uuid(),
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah->id,
            'type' => 'schedule_reminder',
            'title' => 'Pengingat Jadwal: Kajian Akbar',
            'message' => 'Detail kajian',
            'delivery_status' => 'pending',
        ]);

        $fcmMock = \Mockery::mock(\App\Services\FcmService::class);
        $fcmMock->shouldReceive('send')
            ->once()
            ->with('invalid_fcm_token_999', \Mockery::any())
            ->andThrow(new \App\Exceptions\InvalidFcmTokenException('Token invalid'));

        $job = new \App\Jobs\ProcessScheduleReminderJob($schedule, [$notification->id]);
        // Should not throw retry exception for permanent token failure
        $job->handle($fcmMock);

        $notification->refresh();
        $this->assertEquals('failed', $notification->delivery_status);

        $token->refresh();
        $this->assertFalse($token->is_active, 'Invalid token must be deactivated');

        $delivery = \App\Models\NotificationDeviceDelivery::where('notification_id', $notification->id)->first();
        $this->assertNotNull($delivery);
        $this->assertEquals('failed', $delivery->status);
        $this->assertEquals('Token invalid', $delivery->last_error);
    }

    public function test_tlint03_job_retries_transient_failure_and_is_idempotent(): void
    {
        $schedule = Schedule::create([
            'date' => '2026-10-30',
            'time' => '10:00',
            'title' => 'Kajian Akbar',
        ]);

        $jamaah = Jamaah::create(['id' => (string) Str::uuid(), 'full_name' => 'Jamaah Transient']);
        $token = \App\Models\DeviceToken::create([
            'id' => (string) Str::uuid(),
            'tokenable_type' => Jamaah::class,
            'tokenable_id' => $jamaah->id,
            'fcm_token' => 'transient_token_456',
            'platform' => 'android',
            'is_active' => true,
        ]);

        $notification = Notification::create([
            'id' => (string) Str::uuid(),
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah->id,
            'type' => 'schedule_reminder',
            'title' => 'Pengingat Jadwal',
            'message' => 'Detail kajian',
            'delivery_status' => 'pending',
        ]);

        $fcmMock1 = \Mockery::mock(\App\Services\FcmService::class);
        $fcmMock1->shouldReceive('send')
            ->once()
            ->with('transient_token_456', \Mockery::any())
            ->andThrow(new \RuntimeException('FCM request failed with HTTP status 503'));

        $job = new \App\Jobs\ProcessScheduleReminderJob($schedule, [$notification->id]);

        // Attempt 1: Transient failure throws retry exception
        try {
            $job->handle($fcmMock1);
            $this->fail('Expected RuntimeException for retry');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('need retry', $e->getMessage());
        }

        $notification->refresh();
        $this->assertEquals('pending', $notification->delivery_status);
        $token->refresh();
        $this->assertTrue($token->is_active, 'Transient failure must NOT deactivate token');

        $delivery = \App\Models\NotificationDeviceDelivery::where('notification_id', $notification->id)->first();
        $this->assertEquals(1, $delivery->attempts);
        $this->assertEquals('failed', $delivery->status);
        $this->assertStringContainsString('503', $delivery->last_error);

        // Attempt 2: Next retry succeeds (Idempotent without unique constraint violation)
        $fcmMock2 = \Mockery::mock(\App\Services\FcmService::class);
        $fcmMock2->shouldReceive('send')
            ->once()
            ->with('transient_token_456', \Mockery::any())
            ->andReturn(['name' => 'msg_retry_success']);

        $job->handle($fcmMock2);

        $notification->refresh();
        $this->assertEquals('sent', $notification->delivery_status);

        $deliveries = \App\Models\NotificationDeviceDelivery::where('notification_id', $notification->id)->get();
        $this->assertCount(1, $deliveries, 'Idempotent delivery record — must not duplicate on retry');
        $this->assertEquals(2, $deliveries->first()->attempts);
        $this->assertEquals('sent', $deliveries->first()->status);
    }

    public function test_tlint03_failure_of_one_token_does_not_halt_delivery_to_other_recipients(): void
    {
        $schedule = Schedule::create([
            'date' => '2026-10-30',
            'time' => '10:00',
            'title' => 'Kajian Akbar',
        ]);

        $jamaah1 = Jamaah::create(['id' => (string) Str::uuid(), 'full_name' => 'User Token Rusak']);
        $token1 = \App\Models\DeviceToken::create([
            'id' => (string) Str::uuid(),
            'tokenable_type' => Jamaah::class,
            'tokenable_id' => $jamaah1->id,
            'fcm_token' => 'bad_token_001',
            'platform' => 'android',
            'is_active' => true,
        ]);
        $notif1 = Notification::create([
            'id' => (string) Str::uuid(),
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah1->id,
            'type' => 'schedule_reminder',
            'title' => 'Pengingat Jadwal',
            'message' => 'Detail',
            'delivery_status' => 'pending',
        ]);

        $jamaah2 = Jamaah::create(['id' => (string) Str::uuid(), 'full_name' => 'User Token Bagus']);
        $token2 = \App\Models\DeviceToken::create([
            'id' => (string) Str::uuid(),
            'tokenable_type' => Jamaah::class,
            'tokenable_id' => $jamaah2->id,
            'fcm_token' => 'good_token_002',
            'platform' => 'android',
            'is_active' => true,
        ]);
        $notif2 = Notification::create([
            'id' => (string) Str::uuid(),
            'recipient_type' => Jamaah::class,
            'recipient_id' => $jamaah2->id,
            'type' => 'schedule_reminder',
            'title' => 'Pengingat Jadwal',
            'message' => 'Detail',
            'delivery_status' => 'pending',
        ]);

        $fcmMock = \Mockery::mock(\App\Services\FcmService::class);
        $fcmMock->shouldReceive('send')
            ->with('bad_token_001', \Mockery::any())
            ->andThrow(new \App\Exceptions\InvalidFcmTokenException('Bad token'));
        $fcmMock->shouldReceive('send')
            ->with('good_token_002', \Mockery::any())
            ->andReturn(['name' => 'msg_ok']);

        $job = new \App\Jobs\ProcessScheduleReminderJob($schedule, [$notif1->id, $notif2->id]);
        $job->handle($fcmMock);

        $notif1->refresh();
        $notif2->refresh();
        $this->assertEquals('failed', $notif1->delivery_status);
        $this->assertEquals('sent', $notif2->delivery_status, 'Good token must succeed despite bad token on previous recipient');

        $token1->refresh();
        $token2->refresh();
        $this->assertFalse($token1->is_active);
        $this->assertTrue($token2->is_active);
    }
}
