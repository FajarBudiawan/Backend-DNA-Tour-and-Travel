<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Services\ScheduleReminderService;
use App\Services\ScheduleTimelineService;
use Illuminate\Http\Request;

/**
 * @group Perjalanan / Schedule
 *
 * API untuk mengelola data jadwal perjalanan (Timeline) dan pengingat (Reminder).
 * Digunakan oleh Web Admin, Mobile Tour Leader, Mobile Jamaah, dan Mobile Keluarga.
 */
class ScheduleController extends Controller
{
    public function __construct(
        protected ScheduleTimelineService $timelineService,
        protected ScheduleReminderService $reminderService,
    ) {}

    /**
     * Mengambil daftar perjalanan (Timeline)
     *
     * Akses: Admin, Tour Leader, Jamaah, Family.
     * Mengembalikan daftar jadwal yang diurutkan secara kronologis dengan status pelaksanaan
     * dan batas waktu selesai (efektif / diinferensikan).
     *
     * @authenticated
     * @queryParam q string Kata kunci pencarian pada title, location, keterangan, atau pic. Example: Ziarah
     * @queryParam category string Filter berdasarkan kategori kegiatan. Example: Ibadah
     * @queryParam date string Filter berdasarkan tanggal (YYYY-MM-DD). Example: 2026-09-10
     * @queryParam day_number integer Filter berdasarkan nomor hari. Example: 1
     */
    public function index(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if (!$token || (!$token->can('admin') && !$token->can('tour_leader') && !$token->can('jamaah') && !$token->can('family'))) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $schedules = Schedule::query()
            ->when($request->q, function ($query, $q) {
                $query->where(function ($query) use ($q) {
                    $query->where('title', 'like', "%{$q}%")
                        ->orWhere('location', 'like', "%{$q}%")
                        ->orWhere('keterangan', 'like', "%{$q}%")
                        ->orWhere('pic', 'like', "%{$q}%");
                });
            })
            ->when($request->category, function ($query, $category) {
                $query->where('category', $category);
            })
            ->when($request->date, function ($query, $date) {
                $query->whereDate('date', $date);
            })
            ->when($request->day_number, function ($query, $dayNumber) {
                $query->where('day_number', $dayNumber);
            })
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        $resolvedTimeline = $this->timelineService->resolveTimeline($schedules);

        return response()->json([
            'message' => 'Daftar perjalanan berhasil diambil.',
            'data' => ScheduleResource::collection($resolvedTimeline),
        ]);
    }

    /**
     * Menambahkan perjalanan baru
     *
     * Akses: Admin, Tour Leader.
     *
     * @authenticated
     * @bodyParam day_number integer Nomor hari ke berapa. Example: 1
     * @bodyParam date string Format tanggal YYYY-MM-DD. Example: 2026-09-10
     * @bodyParam time string Format waktu mulai HH:MM. Example: 08:00
     * @bodyParam end_time string Format waktu selesai HH:MM (opsional). Example: 10:00
     * @bodyParam title string required Judul kegiatan perjalanan. Example: Ziarah Kota Madinah
     * @bodyParam category string Kategori kegiatan. Example: Ziarah
     * @bodyParam location string Lokasi kegiatan. Example: Masjid Nabawi
     * @bodyParam keterangan string Keterangan/deskripsi kegiatan. Example: Berkumpul di lobby hotel tepat waktu
     * @bodyParam pic string Penanggung jawab kegiatan. Example: Ustadz Ahmad
     * @bodyParam status_override string Status override (upcoming, in_progress, completed). Example: upcoming
     */
    public function store(StoreScheduleRequest $request)
    {
        $data = $request->validated();

        if (!empty($data['status_override'])) {
            $user = $request->user();
            $data['status_mode'] = 'manual';
            $data['status_updated_by_type'] = get_class($user);
            $data['status_updated_by_id'] = (string) $user->id;
            $data['status_updated_at'] = now();
        } else {
            $data['status_mode'] = 'auto';
        }

        $schedule = Schedule::create($data);

        return response()->json([
            'message' => 'Perjalanan berhasil ditambahkan.',
            'data' => new ScheduleResource($schedule),
        ], 201);
    }

    /**
     * Mengambil detail perjalanan
     *
     * Akses: Admin, Tour Leader, Jamaah, Family.
     *
     * @authenticated
     * @urlParam schedule integer required ID Schedule. Example: 1
     */
    public function show(Request $request, Schedule $schedule)
    {
        $token = $request->user()?->currentAccessToken();
        if (!$token || (!$token->can('admin') && !$token->can('tour_leader') && !$token->can('jamaah') && !$token->can('family'))) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json([
            'message' => 'Detail perjalanan berhasil diambil.',
            'data' => new ScheduleResource($schedule),
        ]);
    }

    /**
     * Memperbarui data perjalanan
     *
     * Akses: Admin.
     *
     * @authenticated
     * @urlParam schedule integer required ID Schedule. Example: 1
     * @bodyParam day_number integer Nomor hari ke berapa. Example: 1
     * @bodyParam date string Format tanggal YYYY-MM-DD. Example: 2026-09-10
     * @bodyParam time string Format waktu mulai HH:MM. Example: 09:00
     * @bodyParam end_time string Format waktu selesai HH:MM. Example: 11:00
     * @bodyParam title string Judul kegiatan perjalanan. Example: Ziarah Kota Madinah (Updated)
     * @bodyParam category string Kategori kegiatan. Example: Ziarah
     * @bodyParam location string Lokasi kegiatan. Example: Masjid Nabawi
     * @bodyParam keterangan string Keterangan/deskripsi kegiatan. Example: Berkumpul di lobby hotel
     * @bodyParam pic string Penanggung jawab kegiatan. Example: Ustadz Ahmad
     * @bodyParam status_override string Status override (upcoming, in_progress, completed). Example: in_progress
     * @bodyParam status_mode string Mode status (auto, manual). Example: manual
     */
    public function update(
        UpdateScheduleRequest $request,
        Schedule $schedule
    ) {
        $data = $request->validated();

        if (array_key_exists('status_override', $data)) {
            $user = $request->user();
            if ($data['status_override'] !== null) {
                $data['status_mode'] = $data['status_mode'] ?? 'manual';
                $data['status_updated_by_type'] = get_class($user);
                $data['status_updated_by_id'] = (string) $user->id;
                $data['status_updated_at'] = now();
            } else {
                $data['status_mode'] = 'auto';
            }
        }

        $schedule->update($data);

        return response()->json([
            'message' => 'Perjalanan berhasil diperbarui.',
            'data' => new ScheduleResource($schedule->fresh()),
        ]);
    }

    /**
     * Memperbarui status pelaksanaan perjalanan
     *
     * Akses: Admin, Tour Leader.
     * Mengubah status pelaksanaan secara manual atau mengembalikannya ke mode otomatis.
     *
     * @authenticated
     * @urlParam schedule integer required ID Schedule. Example: 1
     * @bodyParam status_override string Status override (upcoming, in_progress, completed). Kosongkan jika ingin kembali ke auto. Example: in_progress
     * @bodyParam mode string Mode pengelolaan status: 'manual' atau 'auto'. Default: 'manual' jika status_override diisi. Example: manual
     */
    public function updateStatus(Request $request, Schedule $schedule)
    {
        $token = $request->user()?->currentAccessToken();
        if (!$token || (!$token->can('admin') && !$token->can('tour_leader'))) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $request->validate([
            'status_override' => [
                'nullable',
                'in:completed,in_progress,upcoming',
            ],
            'mode' => [
                'nullable',
                'in:auto,manual',
            ],
        ]);

        $user = $request->user();
        $mode = $request->mode ?? ($request->status_override ? 'manual' : 'auto');

        if ($mode === 'auto') {
            $schedule->update([
                'status_override' => null,
                'status_mode' => 'auto',
                'status_updated_by_type' => get_class($user),
                'status_updated_by_id' => (string) $user->id,
                'status_updated_at' => now(),
            ]);
        } else {
            if (!$request->status_override) {
                return response()->json([
                    'message' => 'The status override field is required when mode is manual.',
                    'errors' => [
                        'status_override' => ['The status override field is required.'],
                    ],
                ], 422);
            }

            $schedule->update([
                'status_override' => $request->status_override,
                'status_mode' => 'manual',
                'status_updated_by_type' => get_class($user),
                'status_updated_by_id' => (string) $user->id,
                'status_updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Status perjalanan berhasil diperbarui.',
            'data' => new ScheduleResource($schedule->fresh()),
        ]);
    }

    /**
     * Menyiarkan jadwal perjalanan (Broadcast Reminder)
     *
     * Akses: Tour Leader, Admin.
     * Mencatat aksi siaran dan mengirim pengingat ke penerima yang berhak (Admin, Tour Leader, Jamaah aktif, dan Keluarga aktif).
     *
     * @authenticated
     * @urlParam schedule integer required ID Schedule. Example: 1
     */
    public function broadcast(Request $request, Schedule $schedule)
    {
        $token = $request->user()?->currentAccessToken();
        if (!$token || (!$token->can('admin') && !$token->can('tour_leader'))) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $result = $this->reminderService->broadcast($schedule, $request->user());

        return response()->json([
            'message' => 'Jadwal perjalanan berhasil disiarkan.',
            'data' => [
                'schedule' => new ScheduleResource($schedule->fresh()),
                'broadcast' => $result,
            ],
        ]);
    }

    /**
     * Menghapus perjalanan
     *
     * Akses: Admin.
     *
     * @authenticated
     * @urlParam schedule integer required ID Schedule. Example: 1
     */
    public function destroy(Request $request, Schedule $schedule)
    {
        $token = $request->user()?->currentAccessToken();
        if (!$token || !$token->can('admin')) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $schedule->delete();

        return response()->json([
            'message' => 'Perjalanan berhasil dihapus.',
        ]);
    }
}
