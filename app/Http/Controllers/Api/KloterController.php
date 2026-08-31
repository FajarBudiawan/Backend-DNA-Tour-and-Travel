<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKloterRequest;
use App\Http\Requests\UpdateKloterRequest;
use App\Models\Kloter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KloterController extends Controller
{
    /**
     * Menampilkan semua data kloter keberangkatan (dengan pencarian & filter).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Kloter::with('package')
            ->withCount('registrations');

        // Filter pencarian kode kloter, nama kloter, kode penerbangan, atau nama paket
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('code', 'ilike', "%{$search}%")
                  ->orWhere('flight_code', 'ilike', "%{$search}%")
                  ->orWhereHas('package', function ($pkg) use ($search) {
                      $pkg->where('name', 'ilike', "%{$search}%");
                  });
            });
        }

        // Filter status kloter (draft, active, archived)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan paket
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        $kloters = $query->latest('departure_date')->get();

        return response()->json([
            'message' => 'Data kloter keberangkatan berhasil diambil.',
            'data' => $kloters,
        ]);
    }

    /**
     * Membuat kloter keberangkatan baru.
     */
    public function store(StoreKloterRequest $request): JsonResponse
    {
        $code = $request->code;
        if (!$code) {
            $code = 'KLT-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        }

        $kloter = Kloter::create([
            'name' => $request->name,
            'package_id' => $request->package_id,
            'code' => $code,
            'flight_code' => $request->flight_code,
            'departure_date' => $request->departure_date,
            'return_date' => $request->return_date,
            'hotel_makkah_id' => $request->hotel_makkah_id,
            'hotel_madinah_id' => $request->hotel_madinah_id,
            'status' => $request->status ?? 'draft',
        ]);

        return response()->json([
            'message' => 'Kloter keberangkatan berhasil dibuat.',
            'data' => $kloter->load('package'),
        ], 201);
    }

    /**
     * Menampilkan detail satu kloter keberangkatan.
     */
    public function show(Kloter $kloter): JsonResponse
    {
        return response()->json([
            'message' => 'Detail kloter keberangkatan berhasil diambil.',
            'data' => $kloter->load(['package', 'registrations']),
        ]);
    }

    /**
     * Perbarui data kloter keberangkatan.
     */
    public function update(UpdateKloterRequest $request, Kloter $kloter): JsonResponse
    {
        $kloter->update($request->validated());

        return response()->json([
            'message' => 'Data kloter keberangkatan berhasil diperbarui.',
            'data' => $kloter->load('package'),
        ]);
    }

    /**
     * Hapus data kloter keberangkatan.
     */
    public function destroy(Kloter $kloter): JsonResponse
    {
        if ($kloter->registrations()->count() > 0) {
            return response()->json([
                'message' => 'Kloter tidak dapat dihapus karena sudah memiliki anggota pendaftaran.',
            ], 422);
        }

        $kloter->delete();

        return response()->json([
            'message' => 'Kloter keberangkatan berhasil dihapus.',
        ]);
    }
}
