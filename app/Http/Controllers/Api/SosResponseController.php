<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSosResponseRequest;
use App\Jobs\ProcessSosNotificationJob;
use App\Models\Jamaah;
use App\Models\Notification;
use App\Models\SosIncident;
use App\Models\SosResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
         * Tentukan kolom aktor berdasarkan tipe model autentikasi DAN ability
         * token sekaligus. Pola ini konsisten dengan updateStatus() di
         * SosIncidentController agar token wildcard ["*"] tidak menyebabkan
         * ID InternalUser masuk ke kolom yang mereferensikan tabel yang salah.
         *
         * Urutan pemeriksaan:
         *  1. Jamaah      + ability jamaah       → jamaah_id
         *  2. InternalUser + ability admin       → internal_user_id
         *  3. TourLeader  + ability tour_leader  → tour_leader_id
         */
        if (
            $user instanceof \App\Models\Jamaah
            && $user->currentAccessToken()->can('jamaah')
        ) {
            $responseData['jamaah_id'] = $user->id;
        } elseif (
            $user instanceof \App\Models\InternalUser
            && $user->currentAccessToken()->can('admin')
        ) {
            $responseData['internal_user_id'] = $user->id;
        } elseif (
            $user instanceof \App\Models\TourLeader
            && $user->currentAccessToken()->can('tour_leader')
        ) {
            $responseData['tour_leader_id'] = $user->id;
        } else {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $isStaffResponse = (
            ($user instanceof \App\Models\InternalUser && $user->currentAccessToken()->can('admin'))
            || ($user instanceof \App\Models\TourLeader && $user->currentAccessToken()->can('tour_leader'))
        );

        $result = DB::transaction(function () use ($responseData, $sosIncident, $isStaffResponse) {
            $response = SosResponse::create($responseData);

            $notification = null;
            if ($isStaffResponse && !empty($sosIncident->jamaah_id)) {
                $notification = \App\Models\Notification::create([
                    'sos_incident_id' => $sosIncident->id,
                    'recipient_type' => \App\Models\Jamaah::class,
                    'recipient_id' => $sosIncident->jamaah_id,
                    'type' => 'sos_response_created',
                    'title' => 'Tanggapan Baru untuk Laporan SOS',
                    'message' => 'Petugas telah memberikan tanggapan baru pada laporan darurat Anda.',
                    'delivery_status' => 'pending',
                ]);
            }

            return [
                'response' => $response,
                'notification' => $notification,
            ];
        });

        $response = $result['response'];
        $notification = $result['notification'];

        if ($notification) {
            DB::afterCommit(function () use ($sosIncident, $notification) {
                dispatch(new \App\Jobs\ProcessSosNotificationJob($sosIncident, [$notification->id]));
            });
        }

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