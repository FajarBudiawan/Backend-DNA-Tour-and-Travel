<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJamaahRequest;
use App\Http\Requests\UpdateJamaahRequest;
use App\Models\Jamaah;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JamaahController extends Controller
{
    /**
     * Menampilkan semua data Jamaah resmi.
     * Eager-load relasi package & kloter untuk efisiensi (N+1 prevention).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Jamaah::with(['createdBy', 'package', 'kloter']);

        // Filter pencarian berdasarkan nama, NIK, login_id, nomor paspor, atau telepon
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('login_id', 'ilike', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('passport_number', 'ilike', "%{$search}%");
            });
        }

        // Filter berdasarkan status (active / archived)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan kloter
        if ($request->filled('kloter_id')) {
            $query->where('kloter_id', $request->kloter_id);
        }

        // Filter berdasarkan paket
        if ($request->filled('package_id')) {
            $query->where('package_id', $request->package_id);
        }

        $jamaahList = $query->latest()->get();

        return response()->json([
            'message' => 'Data Jamaah berhasil diambil.',
            'data' => $jamaahList,
        ]);
    }

    /**
     * Membuat data Jamaah baru secara manual oleh admin.
     *
     * Revisi [2026-09-02]: Logic auto-generate "JAMAAH001" dihapus.
     * login_id kini wajib diisi manual oleh admin (max 10 karakter, unique).
     */
    public function store(StoreJamaahRequest $request): JsonResponse
    {
        $jamaah = Jamaah::create([
            // ── Identitas Login ───────────────────────────────────────────────
            'login_id'          => $request->login_id,

            // ── Identitas Pribadi ─────────────────────────────────────────────
            'nik'               => $request->nik,
            'full_name'         => $request->full_name,
            'birth_date'        => $request->birth_date,
            'gender'            => $request->gender,
            'phone'             => $request->phone,
            'emergency_contact' => $request->emergency_contact,

            // ── Dokumen Perjalanan ────────────────────────────────────────────
            'passport_number'   => $request->passport_number,
            'visa_number'       => $request->visa_number,
            'nationality'       => $request->nationality ?? 'Indonesia',

            // ── Relasi Paket & Kloter ─────────────────────────────────────────
            'package_id'        => $request->package_id,
            'kloter_id'         => $request->kloter_id,

            // ── Logistik Perjalanan ───────────────────────────────────────────
            'hotel_makkah'      => $request->hotel_makkah,
            'hotel_madinah'     => $request->hotel_madinah,
            'departure_date'    => $request->departure_date,
            'return_date'       => $request->return_date,

            // ── Pembimbing ────────────────────────────────────────────────────
            'tour_leader'       => $request->tour_leader,
            'mutawif_local'     => $request->mutawif_local,

            // ── Status & Audit ────────────────────────────────────────────────
            'status'            => $request->status ?? 'active',
            'created_by'        => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Data Jamaah berhasil dibuat.',
            'data' => $jamaah->load(['createdBy', 'package', 'kloter']),
        ], 201);
    }

    /**
     * Menampilkan detail satu data Jamaah beserta relasi.
     */
    public function show(Jamaah $jamaah): JsonResponse
    {
        return response()->json([
            'message' => 'Detail Jamaah berhasil diambil.',
            'data' => $jamaah->load(['createdBy', 'package', 'kloter']),
        ]);
    }

    /**
     * Perbarui data profil Jamaah.
     * Semua field baru bisa di-update melalui validated() dari UpdateJamaahRequest.
     */
    public function update(UpdateJamaahRequest $request, Jamaah $jamaah): JsonResponse
    {
        $jamaah->update($request->validated());

        return response()->json([
            'message' => 'Data Jamaah berhasil diperbarui.',
            'data' => $jamaah->fresh()->load(['createdBy', 'package', 'kloter']),
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
