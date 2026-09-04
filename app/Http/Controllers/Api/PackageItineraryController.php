<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\PackageItinerary;
use App\Models\KloterSchedule;
use Illuminate\Http\Request;

class PackageItineraryController extends Controller
{
    /**
     * Display a listing of itineraries for a package.
     */
    public function index(Package $package)
    {
        $itineraries = $package->itineraries()
            ->orderBy('day_number', 'asc')
            ->orderBy('activity_time', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $itineraries
        ]);
    }

    /**
     * Store a newly created itinerary for a package.
     */
    public function store(Request $request, Package $package)
    {
        $validated = $request->validate([
            'day_number'    => 'required|integer|min:1',
            'title'         => 'required|string|max:255',
            'activity_time' => 'nullable|string',
            'location'      => 'nullable|string|max:255',
            'category'      => 'nullable|string|max:100',
            'description'   => 'nullable|string',
        ]);

        $itinerary = $package->itineraries()->create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Template itinerary berhasil ditambahkan.',
            'data'    => $itinerary
        ], 201);
    }

    /**
     * Update the specified itinerary template and sync to un-customized kloter schedules.
     */
    public function update(Request $request, Package $package, PackageItinerary $itinerary)
    {
        if ($itinerary->package_id !== $package->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Template itinerary tidak sesuai dengan paket ini.'
            ], 404);
        }

        $validated = $request->validate([
            'day_number'    => 'sometimes|required|integer|min:1',
            'title'         => 'sometimes|required|string|max:255',
            'activity_time' => 'nullable|string',
            'location'      => 'nullable|string|max:255',
            'category'      => 'nullable|string|max:100',
            'description'   => 'nullable|string',
        ]);

        $itinerary->update($validated);

        // Sync otomatis ke KloterSchedule yang belum dikustomisasi (is_customized = false)
        // Note: activity_date TIDAK disentuh (tetap dihitung dari departure_date kloter masing-masing)
        KloterSchedule::where('source_itinerary_id', $itinerary->id)
            ->where('is_customized', false)
            ->update([
                'title'         => $itinerary->title,
                'location'      => $itinerary->location,
                'category'      => $itinerary->category,
                'activity_time' => $itinerary->activity_time,
                'description'   => $itinerary->description,
                'day_number'    => $itinerary->day_number,
            ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Template itinerary berhasil diperbarui dan disinkronkan ke jadwal kloter.',
            'data'    => $itinerary
        ]);
    }

    /**
     * Remove the specified itinerary template.
     */
    public function destroy(Package $package, PackageItinerary $itinerary)
    {
        if ($itinerary->package_id !== $package->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Template itinerary tidak sesuai dengan paket ini.'
            ], 404);
        }

        $itinerary->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Template itinerary berhasil dihapus.'
        ]);
    }
}
