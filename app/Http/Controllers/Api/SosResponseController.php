<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSosResponseRequest;
use App\Models\SosIncident;
use App\Models\SosResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Modul Darurat / Emergency / SOS
 *
 * API untuk mengelola response / catatan tanggapan penanganan kejadian darurat (SOS Response).
 */
class SosResponseController extends Controller
{
    /**
     * Menampilkan daftar response pada SOS Incident
     *
     * Menampilkan seluruh daftar tanggapan/keterangan penanganan pada satu laporan SOS Incident diurutkan berdasarkan `created_at` dari terlama ke terbaru.
     *
     * **Scope Akses Berdasarkan Role:**
     * Admin, Tour Leader (kloter assigned), dan Jamaah (pemilik SOS) yang memiliki akses ke laporan SOS ini.
     *
     * @authenticated
     * @urlParam sosIncident string required UUID laporan SOS Incident. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
     */
    public function index(
        Request $request,
        SosIncident $sosIncident
    ): JsonResponse {
        if (! $this->canAccessIncident($request, $sosIncident)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $responses = $sosIncident->responses()
            ->with([
                'jamaah:id,full_name',
                'tourLeader:id,full_name',
                'internalUser:id,full_name',
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'data' => $responses,
        ]);
    }

    /**
     * Menambahkan response / keterangan baru pada SOS Incident
     *
     * Menambahkan tanggapan, pesan, atau catatan penanganan baru pada laporan SOS Incident.
     *
     * **Aturan Penentuan Aktor Otomatis:**
     * * Aktor penanggap ditentukan otomatis berdasarkan token autentikasi yang sedang aktif (`jamaah_id`, `tour_leader_id`, atau `internal_user_id`).
     * * Client **tidak boleh** dan **tidak perlu** mengirimkan actor ID di request body.
     *
     * @authenticated
     * @urlParam sosIncident string required UUID laporan SOS Incident. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
     * @bodyParam message string required Pesan atau catatan tanggapan penanganan SOS (maksimal 5000 karakter). Example: Tim medis sudah tiba di lokasi dan penanganan sedang berjalan.
     */
    public function store(
        StoreSosResponseRequest $request,
        SosIncident $sosIncident
    ): JsonResponse {
        if (! $this->canAccessIncident($request, $sosIncident)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $user = $request->user();

        $responseData = [
            'sos_incident_id' => $sosIncident->id,
            'message' => $request->validated('message'),
            'created_at' => now(),
        ];

        /*
         * Actor ditentukan oleh token yang sedang digunakan.
         * Client tidak boleh mengirim actor_id.
         */
        if ($user->currentAccessToken()->can('jamaah')) {
            $responseData['jamaah_id'] = $user->id;
        } elseif ($user->currentAccessToken()->can('tour_leader')) {
            $responseData['tour_leader_id'] = $user->id;
        } elseif ($user->currentAccessToken()->can('admin')) {
            $responseData['internal_user_id'] = $user->id;
        } else {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $response = SosResponse::create($responseData);

        $response->load([
            'jamaah:id,full_name',
            'tourLeader:id,full_name',
            'internalUser:id,full_name',
        ]);

        return response()->json([
            'message' => 'Response SOS berhasil ditambahkan.',
            'data' => $response,
        ], 201);
    }

    /**
     * Memeriksa apakah user memiliki akses terhadap SOS.
     */
    private function canAccessIncident(
        Request $request,
        SosIncident $sosIncident
    ): bool {
        $user = $request->user();

        if ($user->currentAccessToken()->can('admin')) {
            return true;
        }

        if ($user->currentAccessToken()->can('jamaah')) {
            return $sosIncident->jamaah_id === $user->id;
        }

        if ($user->currentAccessToken()->can('tour_leader')) {
            return $user->kloters()
                ->where('kloters.id', $sosIncident->kloter_id)
                ->exists();
        }

        return false;
    }
}