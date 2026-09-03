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
        $query = Kloter::with(['package', 'hotelMakkah', 'hotelMadinah'])
            ->withCount('jamaah'); // diganti dari registrations ke jamaah (single source of truth)

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

        // Filter status kloter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan paket
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        $kloters = $query->latest('created_at')->get();

        return response()->json([
            'message' => 'Data kloter keberangkatan berhasil diambil.',
            'data'    => $kloters,
        ]);
    }

    /**
     * Membuat kloter keberangkatan baru.
     * package_id, departure_date, return_date sekarang NULLABLE (keputusan klien).
     */
    public function store(StoreKloterRequest $request): JsonResponse
    {
        $code = $request->code;
        if (!$code) {
            $code = 'KLT-' . date('Ymd') . '-' . strtoupper(Str::random(4));
        }

        $kloter = Kloter::create([
            'name'             => $request->name,
            'package_id'       => $request->package_id,   // nullable
            'code'             => $code,
            'flight_code'      => $request->flight_code,
            'departure_date'   => $request->departure_date, // nullable
            'return_date'      => $request->return_date,    // nullable
            'hotel_makkah_id'  => $request->hotel_makkah_id,
            'hotel_madinah_id' => $request->hotel_madinah_id,
            'status'           => $request->status ?? 'draft',
            'tour_leader'      => $request->tour_leader,   // plain text
            'mutawif_local'    => $request->mutawif_local, // plain text
        ]);

        return response()->json([
            'message' => 'Kloter keberangkatan berhasil dibuat.',
            'data'    => $kloter->load(['package', 'hotelMakkah', 'hotelMadinah']),
        ], 201);
    }

    /**
     * Menampilkan detail satu kloter keberangkatan beserta daftar jamaah.
     */
    public function show(Kloter $kloter): JsonResponse
    {
        return response()->json([
            'message' => 'Detail kloter keberangkatan berhasil diambil.',
            'data'    => $kloter->load(['package', 'hotelMakkah', 'hotelMadinah', 'jamaah']),
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
            'data'    => $kloter->load(['package', 'hotelMakkah', 'hotelMadinah']),
        ]);
    }

    /**
     * Hapus data kloter keberangkatan.
     *
     * Guard: tidak boleh dihapus jika masih ada jamaah ter-assign via jamaah.kloter_id
     * (single source of truth — menggantikan pengecekan via registrations() lama).
     */
    public function destroy(Kloter $kloter): JsonResponse
    {
        if ($kloter->jamaah()->count() > 0) {
            return response()->json([
                'message' => 'Kloter tidak dapat dihapus karena masih memiliki jamaah yang ter-assign. Pindahkan jamaah ke kloter lain terlebih dahulu.',
            ], 422);
        }

        $kloter->delete();

        return response()->json([
            'message' => 'Kloter keberangkatan berhasil dihapus.',
        ]);
    }

    // TODO-DEPRECATED [2026-09-02]: Semua operasi baca/tulis ke tabel kloter_members
    // telah digantikan oleh jamaah.kloter_id sebagai single source of truth.
    // Tabel kloter_members TIDAK dihapus (untuk rollback safety), tetapi semua
    // logic yang menulis atau membaca ke kloter_members harus berhenti.
    //
    // Berikut adalah daftar operasi yang di-deprecate:
    //
    // public function assignJamaah(Request $request, Kloter $kloter): JsonResponse
    // {
    //     // DEPRECATED: Assign jamaah ke kloter via pivot kloter_members.
    //     // Ganti dengan: Jamaah::find($id)->update(['kloter_id' => $kloter->id])
    //     // melalui endpoint PUT /api/jamaah/{id}.
    //
    //     // $kloter->members()->attach($request->jamaah_id, ['status' => 'active']);
    // }
    //
    // public function removeJamaah(Request $request, Kloter $kloter): JsonResponse
    // {
    //     // DEPRECATED: Lepas jamaah dari kloter via pivot kloter_members.
    //     // Ganti dengan: Jamaah::find($id)->update(['kloter_id' => null])
    //     // melalui endpoint PUT /api/jamaah/{id}.
    //
    //     // $kloter->members()->detach($request->jamaah_id);
    // }
}
