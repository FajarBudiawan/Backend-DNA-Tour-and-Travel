<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    /**
     * Menampilkan daftar hotel (dengan opsional filter per kota).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Hotel::query();

        if ($request->has('city') && !empty($request->city)) {
            $query->where('city', $request->city);
        }

        $hotels = $query->orderBy('name', 'asc')->get();

        return response()->json([
            'message' => 'Daftar hotel berhasil diambil.',
            'data'    => $hotels,
        ]);
    }

    /**
     * Cari hotel berdasarkan nama & kota (case-insensitive exact match).
     * Jika ditemukan: kembalikan hotel yang ada.
     * Jika tidak ditemukan: buat hotel baru dan kembalikan data hotel baru.
     */
    public function findOrCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'city' => 'required|in:Makkah,Madinah',
        ]);

        $name = trim($validated['name']);
        $city = $validated['city'];

        // Cari hotel secara case-insensitive (LOWER(name) & city)
        $existingHotel = Hotel::where('city', $city)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existingHotel) {
            return response()->json([
                'message' => 'Hotel ditemukan.',
                'data'    => $existingHotel,
            ], 200);
        }

        // Buat hotel baru jika belum ada
        $newHotel = Hotel::create([
            'name'   => $name,
            'city'   => $city,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Hotel baru berhasil dibuat.',
            'data'    => $newHotel,
        ], 201);
    }
}
