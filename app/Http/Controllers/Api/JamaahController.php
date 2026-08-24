<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJamaahRequest;
use App\Http\Requests\UpdateJamaahRequest;
use App\Models\Jamaah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JamaahController extends Controller
{
    /**
     * Menampilkan semua data Jamaah resmi.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Jamaah::with('createdBy');

        // Filter pencarian berdasarkan nama, NIK, login_id, atau nomor telepon
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('login_id', 'ilike', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Filter berdasarkan status (active / archived)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $jamaahList = $query->latest()->get();

        return response()->json([
            'message' => 'Data Jamaah berhasil diambil.',
            'data' => $jamaahList,
        ]);
    }

    /**
     * Membuat data Jamaah baru secara langsung.
     */
    public function store(StoreJamaahRequest $request): JsonResponse
    {
        $jamaah = DB::transaction(function () use ($request) {
            // Generate login_id otomatis (contoh: JAMAAH001)
            $lastJamaah = Jamaah::orderBy('created_at', 'desc')->first();

            $nextNumber = 1;
            if ($lastJamaah && preg_match('/(\d+)$/', $lastJamaah->login_id, $matches)) {
                $nextNumber = (int) $matches[1] + 1;
            }

            $loginId = 'JAMAAH' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            return Jamaah::create([
                'login_id' => $loginId,
                'nik' => $request->nik,
                'full_name' => $request->full_name,
                'birth_place' => $request->birth_place,
                'birth_date' => $request->birth_date,
                'gender' => $request->gender,
                'address' => $request->address,
                'phone' => $request->phone,
                'email' => $request->email,
                'emergency_contact_name' => $request->emergency_contact_name,
                'emergency_contact_phone' => $request->emergency_contact_phone,
                'passport_expiry_date' => $request->passport_expiry_date,
                'status' => $request->status ?? 'active',
                'created_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'message' => 'Data Jamaah berhasil dibuat.',
            'data' => $jamaah->load('createdBy'),
        ], 201);
    }

    /**
     * Menampilkan detail satu data Jamaah.
     */
    public function show(Jamaah $jamaah): JsonResponse
    {
        return response()->json([
            'message' => 'Detail Jamaah berhasil diambil.',
            'data' => $jamaah->load('createdBy'),
        ]);
    }

    /**
     * Perbarui data profil Jamaah.
     */
    public function update(UpdateJamaahRequest $request, Jamaah $jamaah): JsonResponse
    {
        $jamaah->update($request->validated());

        return response()->json([
            'message' => 'Data Jamaah berhasil diperbarui.',
            'data' => $jamaah->load('createdBy'),
        ]);
    }

    /**
     * Hapus data Jamaah.
     */
    public function destroy(Jamaah $jamaah): JsonResponse
    {
        $jamaah->delete();

        return response()->json([
            'message' => 'Data Jamaah berhasil dihapus.',
        ]);
    }
}
