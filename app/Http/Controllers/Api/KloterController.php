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

        // Filter pencarian kode kloter atau nama paket
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                  ->orWhereHas('package', function ($pkg) use ($search) {
                      $pkg->where('name', 'ilike', "%{$search}%");
                  });
            });
        }

        // Filter status kloter (draft, ready, active, completed, cancelled)
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
            'package_id' => $request->package_id,
            'code' => $code,
            'departure_date' => $request->departure_date,
            'return_date' => $request->return_date,
            'hotel_makkah_id' => $request->hotel_makkah_id,
            'hotel_madinah_id' => $request->hotel_madinah_id,
            'status' => $request->status ?? 'draft',
            'cancellation_reason' => $request->cancellation_reason,
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
        // Kloter dengan status 'completed' dikunci permanen
        if ($kloter->status === 'completed') {
            return response()->json([
                'message' => 'Kloter dengan status completed tidak dapat diubah.',
            ], 422);
        }

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
