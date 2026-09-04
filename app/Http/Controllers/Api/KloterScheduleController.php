<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kloter;
use App\Models\KloterSchedule;
use App\Models\PackageItinerary;
use Illuminate\Http\Request;
use Carbon\Carbon;

class KloterScheduleController extends Controller
{
    /**
     * Display a listing of schedules for a kloter.
     */
    public function index(Kloter $kloter)
    {
        $schedules = $kloter->schedules()
            ->orderBy('activity_date', 'asc')
            ->orderBy('activity_time', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $schedules
        ]);
    }

    /**
     * Store a manually created schedule for a kloter.
     */
    public function store(Request $request, Kloter $kloter)
    {
        $validated = $request->validate([
            'day_number'     => 'nullable|integer|min:1',
            'activity_date'  => 'required|date',
            'activity_time'  => 'nullable|string',
            'hijri_date_ref' => 'nullable|string|max:30',
            'title'          => 'required|string|max:255',
            'location'       => 'nullable|string|max:255',
            'category'       => 'nullable|string|max:100',
            'pic'            => 'nullable|string|max:255',
            'status'         => 'nullable|string|max:50',
            'description'    => 'nullable|string',
            'is_published'   => 'nullable|boolean',
        ]);

        $validated['kloter_id'] = $kloter->id;
        $validated['source_itinerary_id'] = null;
        $validated['is_customized'] = true; // Jadwal buatan manual dianggap ter-customized

        $schedule = KloterSchedule::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Jadwal kloter berhasil ditambahkan.',
            'data'    => $schedule
        ], 201);
    }

    /**
     * Update the specified schedule for a kloter.
     * Note: Manual update automatically sets is_customized = true.
     */
    public function update(Request $request, Kloter $kloter, KloterSchedule $schedule)
    {
        if ($schedule->kloter_id !== $kloter->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Jadwal tidak sesuai dengan kloter ini.'
            ], 404);
        }

        $validated = $request->validate([
            'day_number'     => 'sometimes|required|integer|min:1',
            'activity_date'  => 'sometimes|required|date',
            'activity_time'  => 'nullable|string',
            'hijri_date_ref' => 'nullable|string|max:30',
            'title'          => 'sometimes|required|string|max:255',
            'location'       => 'nullable|string|max:255',
            'category'       => 'nullable|string|max:100',
            'pic'            => 'nullable|string|max:255',
            'status'         => 'nullable|string|max:50',
            'description'    => 'nullable|string',
            'is_published'   => 'nullable|boolean',
        ]);

        $validated['is_customized'] = true;

        $schedule->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Jadwal kloter berhasil diperbarui (dikustomisasi).',
            'data'    => $schedule
        ]);
    }

    /**
     * Remove the specified schedule from a kloter.
     */
    public function destroy(Kloter $kloter, KloterSchedule $schedule)
    {
        if ($schedule->kloter_id !== $kloter->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Jadwal tidak sesuai dengan kloter ini.'
            ], 404);
        }

        $schedule->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Jadwal kloter berhasil dihapus.'
        ]);
    }

    /**
     * Generate or sync kloter rundown schedules from Package Itinerary templates.
     */
    public function generateFromTemplate(Kloter $kloter)
    {
        if (!$kloter->package_id || !$kloter->departure_date) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal generate: Kloter belum memiliki Paket Umrah atau Tanggal Keberangkatan (departure_date).'
            ], 422);
        }

        $itineraries = PackageItinerary::where('package_id', $kloter->package_id)
            ->orderBy('day_number', 'asc')
            ->orderBy('activity_time', 'asc')
            ->get();

        if ($itineraries->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paket Umrah terkait belum memiliki template itinerary.'
            ], 422);
        }

        $departureDate = Carbon::parse($kloter->departure_date);

        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($itineraries as $itinerary) {
            $dayOffset = ($itinerary->day_number ?? 1) - 1;
            $activityDate = $departureDate->copy()->addDays($dayOffset)->format('Y-m-d');

            $existing = KloterSchedule::where('kloter_id', $kloter->id)
                ->where('source_itinerary_id', $itinerary->id)
                ->first();

            if (!$existing) {
                KloterSchedule::create([
                    'kloter_id'           => $kloter->id,
                    'source_itinerary_id' => $itinerary->id,
                    'day_number'          => $itinerary->day_number,
                    'activity_date'       => $activityDate,
                    'activity_time'       => $itinerary->activity_time,
                    'title'               => $itinerary->title,
                    'location'            => $itinerary->location,
                    'category'            => $itinerary->category,
                    'description'         => $itinerary->description,
                    'is_customized'       => false,
                    'status'              => 'belum_mulai',
                ]);
                $createdCount++;
            } elseif (!$existing->is_customized) {
                $existing->update([
                    'day_number'    => $itinerary->day_number,
                    'activity_date' => $activityDate,
                    'activity_time' => $itinerary->activity_time,
                    'title'         => $itinerary->title,
                    'location'      => $itinerary->location,
                    'category'      => $itinerary->category,
                    'description'   => $itinerary->description,
                ]);
                $updatedCount++;
            } else {
                // Existing schedule exists and is_customized == true
                $skippedCount++;
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Generate rundown kloter dari template berhasil.',
            'data'    => [
                'created'        => $createdCount,
                'updated'        => $updatedCount,
                'skipped'        => $skippedCount,
                'total_template' => $itineraries->count(),
            ]
        ]);
    }
}
