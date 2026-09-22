<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\JamaahScheduleResource;
use App\Models\KloterSchedule;
use Illuminate\Http\Request;

class JamaahScheduleController extends Controller
{
    /**
     * Display published schedules for the authenticated jamaah's kloter.
     */
    public function index(Request $request)
    {
        $jamaah = $request->user();

        if (!$jamaah->kloter_id) {
            return response()->json([
                'status' => 'success',
                'message' => 'Jamaah belum terdaftar di kloter mana pun.',
                'data' => [],
            ]);
        }

        $schedules = KloterSchedule::query()
            ->where('kloter_id', $jamaah->kloter_id)
            ->where('is_published', true)
            ->orderBy('activity_date', 'asc')
            ->orderBy('activity_time', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal kegiatan perjalanan berhasil diambil.',
            'data' => JamaahScheduleResource::collection($schedules),
        ]);
    }
}
