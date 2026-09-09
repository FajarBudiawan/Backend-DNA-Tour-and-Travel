<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use Illuminate\Http\Request;


/**
 * @group Perjalanan / Schedule
 *
 * API untuk mengelola data jadwal perjalanan.
 */
class ScheduleController extends Controller
{
    /**
     * Mengambil daftar perjalanan
     *
     * Mendukung search dan filter berdasarkan query parameter.
     *
     * @queryParam q string Kata kunci pencarian pada title, location, keterangan, atau pic. Example: Ziarah
     * @queryParam category string Filter berdasarkan kategori kegiatan. Example: Ibadah
     * @queryParam date string Filter berdasarkan tanggal (YYYY-MM-DD). Example: 2026-09-10
     * @queryParam day_number integer Filter berdasarkan nomor hari. Example: 1
     */
    public function index(Request $request)
    {
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

        return response()->json([
            'message' => 'Daftar perjalanan berhasil diambil.',
            'data' => ScheduleResource::collection($schedules),
        ]);
    }

    /**
     * Menambahkan perjalanan baru
     *
     * @bodyParam day_number integer Nomor hari ke berapa. Example: 1
     * @bodyParam date string Format tanggal YYYY-MM-DD. Example: 2026-09-10
     * @bodyParam time string Format waktu HH:MM. Example: 08:00
     * @bodyParam title string Judul kegiatan perjalanan. Example: Ziarah Kota Madinah
     * @bodyParam category string Kategori kegiatan. Example: Ziarah
     * @bodyParam location string Lokasi kegiatan. Example: Masjid Nabawi
     * @bodyParam keterangan string Keterangan/deskripsi kegiatan. Example: Berkumpul di lobby hotel tepat waktu
     * @bodyParam pic string Person in charge / penanggung jawab. Example: Ustadz Ahmad
     * @bodyParam status_override string Status override jika ingin menentukan status secara manual (upcoming, in_progress, completed). Example: upcoming
     */
    public function store(StoreScheduleRequest $request)
    {
        $schedule = Schedule::create($request->validated());

        return response()->json([
            'message' => 'Perjalanan berhasil ditambahkan.',
            'data' => new ScheduleResource($schedule),
        ], 201);
    }

    /**
     * Mengambil detail perjalanan
     *
     * @urlParam schedule integer required ID Schedule. Example: 1
     */
    public function show(Schedule $schedule)
    {
        return response()->json([
            'message' => 'Detail perjalanan berhasil diambil.',
            'data' => new ScheduleResource($schedule),
        ]);
    }

    /**
     * Memperbarui data perjalanan
     *
     * @urlParam schedule integer required ID Schedule. Example: 1
     * @bodyParam day_number integer Nomor hari ke berapa. Example: 1
     * @bodyParam date string Format tanggal YYYY-MM-DD. Example: 2026-09-10
     * @bodyParam time string Format waktu HH:MM. Example: 09:00
     * @bodyParam title string Judul kegiatan perjalanan. Example: Ziarah Kota Madinah (Updated)
     * @bodyParam category string Kategori kegiatan. Example: Ziarah
     * @bodyParam location string Lokasi kegiatan. Example: Masjid Nabawi
     * @bodyParam keterangan string Keterangan/deskripsi kegiatan. Example: Berkumpul di lobby hotel
     * @bodyParam pic string Person in charge / penanggung jawab. Example: Ustadz Ahmad
     * @bodyParam status_override string Status override (upcoming, in_progress, completed). Example: in_progress
     */
    public function update(
        UpdateScheduleRequest $request,
        Schedule $schedule
    ) {
        $schedule->update($request->validated());

        return response()->json([
            'message' => 'Perjalanan berhasil diperbarui.',
            'data' => new ScheduleResource($schedule->fresh()),
        ]);
    }

    /**
     * Memperbarui status override perjalanan
     *
     * @urlParam schedule integer required ID Schedule. Example: 1
     * @bodyParam status_override string required Status override baru (upcoming, in_progress, completed). Example: in_progress
     */
    public function updateStatus(Request $request, Schedule $schedule)
    {
        $request->validate([
            'status_override' => [
                'required',
                'in:completed,in_progress,upcoming',
            ],
        ]);

        $schedule->update([
            'status_override' => $request->status_override,
        ]);

        return response()->json([
            'message' => 'Status perjalanan berhasil diperbarui.',
            'data' => new ScheduleResource($schedule->fresh()),
        ]);
    }

    /**
     * Menghapus perjalanan
     *
     * @urlParam schedule integer required ID Schedule. Example: 1
     */
    public function destroy(Schedule $schedule)
    {
        $schedule->delete();

        return response()->json([
            'message' => 'Perjalanan berhasil dihapus.',
        ]);
    }
}