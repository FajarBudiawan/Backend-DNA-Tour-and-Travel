<?php

namespace App\Http\Controllers\Api;

use App\Events\SosCreated;
use App\Events\SosStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSosIncidentRequest;
use App\Http\Requests\UpdateSosIncidentStatusRequest;
use App\Models\SosIncident;
use App\Models\SosStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Modul Darurat / Emergency / SOS
 *
 * API untuk mengelola laporan kejadian darurat (SOS Incident) dari Jamaah dan penanganannya oleh Admin/Tour Leader.
 */
class SosIncidentController extends Controller
{
    /**
     * Menampilkan daftar SOS Incident
     *
     * Menampilkan daftar laporan SOS yang dapat diakses oleh user sesuai ability token autentikasi.
     *
     * **Scope Akses Berdasarkan Role:**
     * * **Admin (`admin`):** Memiliki akses penuh untuk melihat seluruh laporan SOS dari semua kloter.
     * * **Tour Leader (`tour_leader`):** Hanya dapat melihat laporan SOS dari kloter yang ditugaskan kepadanya.
     * * **Jamaah (`jamaah`):** Hanya dapat melihat laporan SOS miliknya sendiri.
     *
     * Data diurutkan berdasarkan `triggered_at` terbaru dan menyertakan eager-load relasi `jamaah` (id, full_name, phone), `kloter` (id, name), serta jumlah tanggapan (`responses_count`).
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = SosIncident::with([
            'jamaah:id,full_name,phone',
            'kloter:id,name',
        ])
            ->withCount('responses')
            ->latest('triggered_at');

        if ($user->currentAccessToken()->can('admin')) {
            // Admin dapat melihat semua SOS.
        } elseif ($user->currentAccessToken()->can('tour_leader')) {
            $kloterIds = $user->kloters()
                ->pluck('kloters.id');

            $query->whereIn('kloter_id', $kloterIds);
        } elseif ($user->currentAccessToken()->can('jamaah')) {
            $query->where('jamaah_id', $user->id);
        } else {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        // ✅ FIX #5: Wrap response dengan { message, data } untuk consistency API
        return response()->json([
            'message' => 'SOS incidents retrieved successfully',
            'data' => $query->paginate(50)
        ]);
    }

    /**
     * Menampilkan detail satu SOS Incident
     *
     * Menampilkan rincian informasi satu laporan kejadian SOS Incident beserta relasi Jamaah, Kloter, Responses, dan Status Histories.
     *
     * **Scope Akses Berdasarkan Role:**
     * * **Admin (`admin`):** Dapat melihat detail laporan SOS manapun.
     * * **Tour Leader (`tour_leader`):** Hanya dapat melihat detail SOS jika kloter incident sesuai dengan kloter assignment-nya.
     * * **Jamaah (`jamaah`):** Hanya dapat melihat detail SOS miliknya sendiri.
     *
     * @authenticated
     * @urlParam sosIncident string required UUID laporan SOS Incident. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
     */
    public function show(
        Request $request,
        SosIncident $sosIncident
    ): JsonResponse {
        if (! $this->canAccessIncident($request, $sosIncident)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        $sosIncident->load([
            'jamaah:id,full_name,phone',
            'kloter:id,name',
            'responses.jamaah:id,full_name',
            'responses.tourLeader:id,full_name',
            'responses.internalUser:id,full_name',
            'statusHistories.internalUser:id,full_name',
            'statusHistories.tourLeader:id,full_name',
        ]);

        return response()->json([
            'data' => $sosIncident,
        ]);
    }

    /**
     * Membuat laporan SOS baru
     *
     * Membuat laporan kejadian darurat (SOS Incident) baru.
     *
     * **Aturan Autentikasi & Authorization:**
     * * **Jamaah (`jamaah`):** Dapat membuat SOS untuk dirinya sendiri. Field `jamaah_id` **tidak perlu dikirim** oleh client karena diisi otomatis dari token Jamaah.
     * * **Admin (`admin`):** Dapat membuat SOS atas nama Jamaah dan **wajib menentukan** `jamaah_id` di request body. Jika `jamaah_id` kosong/tidak disertakan, mengembalikan response error `422 Unprocessable Entity`.
     * * **Tour Leader (`tour_leader`):** **Tidak diperbolehkan** membuat SOS dan menghasilkan response error `403 Forbidden` (`Tour Leader tidak dapat membuat SOS.`).
     *
     * **Aturan Penentuan Field Otomatis oleh Backend:**
     * * `kloter_id`: Diambil otomatis dari `jamaah.kloter_id`.
     * * `status`: Bernilai awal `'triggered'`.
     * * `triggered_at`: Diisi otomatis dengan timestamp `now()`.
     * * Record awal pada `sos_status_histories` dibuat otomatis (`old_status: null`, `new_status: 'triggered'`).
     *
     * @authenticated
     * @bodyParam jamaah_id string UUID Jamaah yang mengalami darurat. Wajib dikirim oleh Admin, diabaikan/diisi otomatis jika login sebagai Jamaah. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
     * @bodyParam type string required Jenis kejadian darurat (misal: "Darurat Medis", "Tersesat", "Kecelakaan"). Example: Darurat Medis
     * @bodyParam description string Rincian deskripsi kondisi darurat yang dialami. Example: Jamaah mengalami pusing dan sesak napas.
     * @bodyParam location_name string Nama lokasi atau deskripsi landmark tempat kejadian. Example: Pelataran Tawaf Gate 1
     * @bodyParam latitude number required Koordinat latitude lokasi kejadian (-90 s/d 90). Example: 21.4225000
     * @bodyParam longitude number required Koordinat longitude lokasi kejadian (-180 s/d 180). Example: 39.8262000
     */
    public function store(
        StoreSosIncidentRequest $request
    ): JsonResponse {
        $user = $request->user();
        $token = $user->currentAccessToken();

        /*
         * Pastikan token memiliki ability yang memang
         * diperbolehkan untuk mengakses fitur SOS.
         */
        if (
            ! $token->can('admin') &&
            ! $token->can('jamaah') &&
            ! $token->can('tour_leader')
        ) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 403);
        }

        $data = $request->validated();

        /*
         * Jamaah tidak boleh menentukan jamaah_id sendiri.
         * ID Jamaah diambil dari user yang sedang login.
         */
        if ($token->can('jamaah')) {
            $data['jamaah_id'] = $user->id;
        }

        /*
         * Admin wajib menentukan jamaah_id.
         */
        if (
            $token->can('admin')
            && empty($data['jamaah_id'])
        ) {
            return response()->json([
                'message' => 'jamaah_id is required for admin.',
            ], 422);
        }

        /*
         * Tour Leader tidak boleh membuat SOS.
         */
        if ($token->can('tour_leader')) {
            return response()->json([
                'message' => 'Tour Leader tidak dapat membuat SOS.',
            ], 403);
        }

        /*
         * Ambil kloter dari Jamaah agar client tidak dapat
         * mengirim kloter_id yang berbeda.
         */
        $jamaah = \App\Models\Jamaah::find($data['jamaah_id']);

        if (! $jamaah) {
            return response()->json([
                'message' => 'Jamaah tidak ditemukan.',
            ], 422);
        }

        // Cek duplicate: Jamaah sudah punya SOS aktif?
        $existingActiveSos = SosIncident::where('jamaah_id', $data['jamaah_id'])
            ->whereNotIn('status', ['resolved', 'false_alarm'])
            ->first();

        if ($existingActiveSos) {
            return response()->json([
                'message' => 'Jamaah sudah memiliki laporan SOS aktif.',
                'data' => [
                    'existing_incident_id' => $existingActiveSos->id,
                    'existing_status' => $existingActiveSos->status,
                ]
            ], 409);  // Conflict
        }

        $data['kloter_id'] = $jamaah->kloter_id;
        $data['status'] = 'triggered';
        $data['triggered_at'] = now();

        $incident = DB::transaction(function () use ($data) {
            $incident = SosIncident::create($data);

            SosStatusHistory::create([
                'sos_incident_id' => $incident->id,
                'old_status' => null,
                'new_status' => 'triggered',
                'changed_at' => now(),
            ]);

            return $incident;
        });

        // DISPATCH EVENT OUTSIDE TRANSACTION
        DB::afterCommit(function () use ($incident) {
            event(new SosCreated($incident, auth()->user()));
        });

        $incident->load([
            'jamaah:id,full_name,phone',
            'kloter:id,name',
        ]);

        return response()->json([
            'message' => 'SOS berhasil dibuat.',
            'data' => $incident,
        ], 201);
    }

    /**
     * Mengubah status SOS Incident
     *
     * Memperbarui status penanganan laporan SOS Incident dan mencatat riwayat perubahannya ke `sos_status_histories`.
     *
     * **Aturan Hak Akses:**
     * * **Jamaah (`jamaah`):** **Tidak dapat** mengubah status SOS. Menghasilkan response `403 Forbidden` (`Jamaah tidak dapat mengubah status SOS.`).
     * * **Admin (`admin`):** Dapat mengubah status SOS mana saja jika memiliki akses. Aktor pencatat pada `sos_status_histories` disimpan di kolom `changed_by`.
     * * **Tour Leader (`tour_leader`):** Dapat mengubah status SOS jika memiliki akses terhadap incident (kloter assignment). Aktor pencatat pada `sos_status_histories` disimpan di kolom `tour_leader_id`.
     *
     * **Nilai Status yang Diperbolehkan:**
     * * `triggered`: Laporan SOS baru terpicu.
     * * `acknowledged`: Laporan telah diterima/dikonfirmasi oleh Petugas/TL.
     * * `in_action`: Petugas/TL sedang dalam penanganan di lapangan.
     * * `resolved`: Kejadian darurat telah selesai ditangani.
     * * `false_alarm`: Laporan palsu / tidak sengaja tertekan.
     *
     * @authenticated
     * @urlParam sosIncident string required UUID laporan SOS Incident. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d
     * @bodyParam status string required Status baru yang valid (`triggered`, `acknowledged`, `in_action`, `resolved`, `false_alarm`). Example: acknowledged
     */

public function updateStatus(
    UpdateSosIncidentStatusRequest $request,
    SosIncident $sosIncident
): JsonResponse {
    if (! $this->canAccessIncident($request, $sosIncident)) {
        return response()->json([
            'message' => 'Forbidden.',
        ], 403);
    }

    $user = $request->user();
    $token = $user->currentAccessToken();

    // Hanya Admin dan Tour Leader yang boleh mengubah status.
    if (
        ! $token->can('admin') &&
        ! $token->can('tour_leader')
    ) {
        return response()->json([
            'message' => 'Jamaah tidak dapat mengubah status SOS.',
        ], 403);
    }

    $newStatus = $request->validated('status');

    // Definisi transisi status yang diperbolehkan.
    $allowedTransitions = [
        'triggered' => ['acknowledged', 'false_alarm'],
        'acknowledged' => ['in_action', 'false_alarm'],
        'in_action' => ['resolved', 'false_alarm'],
        'resolved' => [],
        'false_alarm' => [],
    ];

    // Validasi dilakukan di dalam transaksi terhadap status terbaru.
    $result = DB::transaction(function () use (
        $sosIncident,
        $newStatus,
        $user,
        $allowedTransitions,
        $request
    ) {
        $incident = SosIncident::query()
            ->whereKey($sosIncident->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $oldStatus = $incident->status;

        // Permintaan status yang sama tidak membuat riwayat baru.
        if ($oldStatus === $newStatus) {
            return [
                'changed' => false,
                'invalid_transition' => false,
                'incident' => $incident,
                'old_status' => $oldStatus,
            ];
        }

        // Tolak transisi yang tidak diizinkan.
        if (! in_array(
            $newStatus,
            $allowedTransitions[$oldStatus] ?? [],
            true
        )) {
            return [
                'changed' => false,
                'invalid_transition' => true,
                'incident' => $incident,
                'old_status' => $oldStatus,
            ];
        }

        // Perbarui status dan timestamp terkait.
        $incident->status = $newStatus;

        if ($newStatus === 'acknowledged') {
            $incident->acknowledged_at ??= now();
        }

        if ($newStatus === 'resolved') {
            $incident->resolved_at ??= now();
        }

        $incident->save();

        // Simpan riwayat dalam transaksi yang sama.
        $historyData = [
            'sos_incident_id' => $incident->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $request->validated('reason'),
            'changed_at' => now(),
        ];

        if ($user->currentAccessToken()->can('tour_leader')) {
            $historyData['tour_leader_id'] = $user->id;
        } elseif ($user->currentAccessToken()->can('admin')) {
            $historyData['changed_by'] = $user->id;
        }

        SosStatusHistory::create($historyData);

        return [
            'changed' => true,
            'invalid_transition' => false,
            'incident' => $incident,
            'old_status' => $oldStatus,
        ];
    });

    // Respons khusus jika transisi status tidak valid.
    if ($result['invalid_transition']) {
        return response()->json([
            'message' => 'Transisi status SOS tidak diizinkan.',
            'current_status' => $result['old_status'],
            'requested_status' => $newStatus,
        ], 422);
    }

    $incident = $result['incident'];

    // Kirim event setelah transaksi database selesai.
    if ($result['changed']) {
        event(new SosStatusUpdated(
            $incident->fresh(),
            $result['old_status'],
            $newStatus,
            $user,
            $request->validated('reason')
        ));
    }

    return response()->json([
        'message' => $result['changed']
            ? 'Status SOS berhasil diperbarui.'
            : 'Status SOS sudah berada pada status tersebut.',
        'data' => $incident->fresh(),
    ]);
}

    /**
     * Memeriksa apakah user memiliki akses terhadap incident.
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